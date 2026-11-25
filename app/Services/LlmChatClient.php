<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class LlmChatClient
{
    public function __construct(private LlmResponseCleaner $cleaner) {}

    /**
     * Call chat completions: Gemini first, then Groq, then NVIDIA.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options
     */
    public function chat(array $messages, array $options = []): string
    {
        $wantsVision = $this->messagesContainImage($messages);
        $providers = $this->providers($wantsVision);

        if ($providers === []) {
            throw new RuntimeException('No AI provider is configured. Set GEMINI_API_KEY (or GROQ_API_KEY / NVIDIA_API_KEY).');
        }

        $lastError = null;
        foreach ($providers as $provider) {
            $models = $provider['models'] ?? [$provider['model']];
            foreach ($models as $model) {
                $providerWithModel = array_merge($provider, ['model' => $model]);
                try {
                    $raw = $provider['name'] === 'gemini'
                        ? $this->requestGemini($providerWithModel, $messages, $options)
                        : $this->requestOpenAiCompatible($providerWithModel, $messages, $options);

                    $cleaned = $this->cleaner->clean($raw);
                    if ($cleaned === '') {
                        throw new RuntimeException($provider['name'].' returned an empty completion after cleaning.');
                    }

                    return $cleaned;
                } catch (\Throwable $e) {
                    $lastError = $e;
                    Log::warning('LLM provider failed; trying next', [
                        'provider' => $provider['name'],
                        'model' => $model,
                        'error' => $e->getMessage(),
                    ]);

                    // Do not retry other models on the same provider if the API key itself was rejected.
                    if (preg_match('/HTTP (?:401|403)\b|API key not valid|invalid_api_key/i', $e->getMessage()) === 1) {
                        break;
                    }
                }
            }
        }

        throw new RuntimeException(
            'All AI providers failed.'.($lastError ? ' Last error: '.$lastError->getMessage() : ''),
            0,
            $lastError,
        );
    }

    /**
     * @return array<int, array{name:string,api_key:string,endpoint?:string,base_url?:string,model:string,models:list<string>}>
     */
    private function providers(bool $wantsVision): array
    {
        $providers = [];

        $geminiKey = trim((string) config('services.gemini.api_key', ''));
        if ($geminiKey !== '') {
            $configured = $wantsVision
                ? (string) config('services.gemini.vision_model')
                : (string) config('services.gemini.text_model');
            $primary = $this->normalizeModel('gemini', $configured, $wantsVision);
            $providers[] = [
                'name' => 'gemini',
                'api_key' => $geminiKey,
                'base_url' => (string) config('services.gemini.base_url'),
                'model' => $primary,
                'models' => array_values(array_unique([
                    $primary,
                    'gemini-2.5-flash',
                    'gemini-2.5-flash-lite',
                    'gemini-flash-latest',
                ])),
            ];
        }

        $groqKey = trim((string) config('services.groq.api_key', ''));
        if ($groqKey !== '') {
            $configured = $wantsVision
                ? (string) config('services.groq.vision_model')
                : (string) config('services.groq.text_model');
            $primary = $this->normalizeModel('groq', $configured, $wantsVision);
            $fallbackModels = $wantsVision
                ? [$primary, 'meta-llama/llama-4-scout-17b-16e-instruct']
                : [$primary, 'llama-3.3-70b-versatile', 'llama-3.1-8b-instant'];
            $providers[] = [
                'name' => 'groq',
                'api_key' => $groqKey,
                'endpoint' => (string) config('services.groq.chat_endpoint'),
                'model' => $primary,
                'models' => array_values(array_unique($fallbackModels)),
            ];
        }

        $nvidiaKey = trim((string) config('services.nvidia.api_key', ''));
        if ($nvidiaKey !== '') {
            $configured = $wantsVision
                ? (string) config('services.nvidia.vision_model')
                : (string) config('services.nvidia.text_model');
            $primary = $this->normalizeModel('nvidia', $configured, $wantsVision);
            $fallbackModels = $wantsVision
                ? [$primary]
                : [$primary, 'meta/llama-3.1-70b-instruct', 'meta/llama-3.1-8b-instruct'];
            $providers[] = [
                'name' => 'nvidia',
                'api_key' => $nvidiaKey,
                'endpoint' => (string) config('services.nvidia.chat_endpoint'),
                'model' => $primary,
                'models' => array_values(array_unique($fallbackModels)),
            ];
        }

        return $providers;
    }

    /**
     * Remap retired/deprecated model slugs that may still exist in production .env files.
     */
    private function normalizeModel(string $provider, string $model, bool $wantsVision): string
    {
        $trimmed = trim($model);

        if ($provider === 'gemini') {
            $retiredGemini = [
                '',
                'gemini-2.0-flash',
                'gemini-2.0-flash-exp',
                'gemini-2.0-flash-lite',
                'gemini-1.5-flash',
                'gemini-1.5-flash-latest',
                'gemini-1.5-pro',
            ];
            if (in_array(strtolower($trimmed), $retiredGemini, true)) {
                return 'gemini-2.5-flash';
            }

            return $trimmed;
        }

        if ($provider === 'groq') {
            $retiredGroq = [
                '',
                'qwen/qwen3.6-27b',
                'llama3-70b-8192',
                'llama3-8b-8192',
            ];
            if (in_array(strtolower($trimmed), $retiredGroq, true)) {
                return $wantsVision
                    ? 'meta-llama/llama-4-scout-17b-16e-instruct'
                    : 'llama-3.3-70b-versatile';
            }

            return $trimmed;
        }

        if ($provider === 'nvidia') {
            $retiredNvidia = [
                '',
                'meta/llama-3.3-70b-instruct',
            ];
            if (in_array(strtolower($trimmed), $retiredNvidia, true)) {
                return $wantsVision
                    ? 'meta/llama-3.2-11b-vision-instruct'
                    : 'meta/llama-3.1-70b-instruct';
            }

            return $trimmed;
        }

        return $trimmed;
    }

    /**
     * @param  array{name:string,api_key:string,base_url:string,model:string}  $provider
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options
     */
    private function requestGemini(array $provider, array $messages, array $options): string
    {
        $timeout = (int) ($options['timeout'] ?? 90);
        $requestedMaxTokens = (int) ($options['max_tokens'] ?? 2048);
        [$system, $contents] = $this->toGeminiContents($messages);
        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => (float) ($options['temperature'] ?? 0.5),
                // Ensure sufficient output budget for Gemini 2.5 thinking + visible reply.
                'maxOutputTokens' => max(2048, $requestedMaxTokens),
            ],
        ];
        if ($system !== '') {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $system]],
            ];
        }

        $url = rtrim($provider['base_url'], '/').'/models/'.$provider['model'].':generateContent';

        Log::info('LLM chat request', [
            'provider' => 'gemini',
            'model' => $provider['model'],
            'message_count' => count($messages),
        ]);

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout(min(15, $timeout))
                ->withHeaders([
                    'x-goog-api-key' => $provider['api_key'],
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException('gemini connection failed: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                'gemini HTTP '.$response->status().': '.
                (string) data_get($response->json(), 'error.message', 'provider_error'),
            );
        }

        $parts = data_get($response->json(), 'candidates.0.content.parts', []);
        $chunks = [];
        if (is_array($parts)) {
            foreach ($parts as $part) {
                if (! is_array($part)) {
                    continue;
                }
                // Skip Gemini "thought" parts when present.
                if (! empty($part['thought'])) {
                    continue;
                }
                $text = trim((string) ($part['text'] ?? ''));
                if ($text !== '') {
                    $chunks[] = $text;
                }
            }
        }

        $content = trim(implode("\n", $chunks));
        if ($content === '') {
            throw new RuntimeException('gemini returned an empty completion.');
        }

        return $content;
    }

    /**
     * @param  array{name:string,api_key:string,endpoint:string,model:string}  $provider
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options
     */
    private function requestOpenAiCompatible(array $provider, array $messages, array $options): string
    {
        $timeout = (int) ($options['timeout'] ?? 90);
        $payload = [
            'model' => $provider['model'],
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.5,
            'max_tokens' => $options['max_tokens'] ?? 1024,
        ];

        if (! empty($options['response_format'])) {
            $payload['response_format'] = $options['response_format'];
        }

        Log::info('LLM chat request', [
            'provider' => $provider['name'],
            'model' => $provider['model'],
            'message_count' => count($messages),
        ]);

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout(min(15, $timeout))
                ->withToken($provider['api_key'])
                ->acceptJson()
                ->post($provider['endpoint'], $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException($provider['name'].' connection failed: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                $provider['name'].' HTTP '.$response->status().': '.
                (string) data_get($response->json(), 'error.message', 'provider_error'),
            );
        }

        $content = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
        if ($content === '') {
            throw new RuntimeException($provider['name'].' returned an empty completion.');
        }

        return $content;
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array{0:string,1:list<array<string,mixed>>}
     */
    private function toGeminiContents(array $messages): array
    {
        $system = '';
        $contents = [];

        foreach ($messages as $message) {
            $role = (string) ($message['role'] ?? 'user');
            $content = $message['content'] ?? '';

            if ($role === 'system') {
                $system .= ($system === '' ? '' : "\n\n").(is_string($content) ? $content : json_encode($content));

                continue;
            }

            $geminiRole = $role === 'assistant' ? 'model' : 'user';
            $parts = [];

            if (is_string($content)) {
                $parts[] = ['text' => $content];
            } elseif (is_array($content)) {
                foreach ($content as $part) {
                    if (! is_array($part)) {
                        continue;
                    }
                    if (($part['type'] ?? null) === 'text') {
                        $parts[] = ['text' => (string) ($part['text'] ?? '')];
                    } elseif (($part['type'] ?? null) === 'image_url') {
                        $url = (string) data_get($part, 'image_url.url', '');
                        if (preg_match('/^data:([^;]+);base64,(.+)$/', $url, $match) === 1) {
                            $parts[] = [
                                'inline_data' => [
                                    'mime_type' => $match[1],
                                    'data' => $match[2],
                                ],
                            ];
                        }
                    }
                }
            }

            if ($parts !== []) {
                $lastIndex = count($contents) - 1;
                if ($lastIndex >= 0 && $contents[$lastIndex]['role'] === $geminiRole) {
                    $contents[$lastIndex]['parts'] = array_merge($contents[$lastIndex]['parts'], $parts);
                } else {
                    $contents[] = ['role' => $geminiRole, 'parts' => $parts];
                }
            }
        }

        while ($contents !== [] && $contents[0]['role'] !== 'user') {
            array_shift($contents);
        }

        if ($contents === []) {
            $contents[] = ['role' => 'user', 'parts' => [['text' => 'Hello']]];
        }

        return [$system, $contents];
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     */
    private function messagesContainImage(array $messages): bool
    {
        foreach ($messages as $message) {
            $content = $message['content'] ?? null;
            if (! is_array($content)) {
                continue;
            }
            foreach ($content as $part) {
                if (($part['type'] ?? null) === 'image_url') {
                    return true;
                }
            }
        }

        return false;
    }
}
