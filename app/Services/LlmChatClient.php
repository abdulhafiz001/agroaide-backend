<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class LlmChatClient
{
    /**
     * Call chat completions with NVIDIA first, then Groq as backup.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options
     */
    public function chat(array $messages, array $options = []): string
    {
        $wantsVision = $this->messagesContainImage($messages);
        $providers = $this->providers($wantsVision);

        if ($providers === []) {
            throw new RuntimeException('No AI provider is configured. Set NVIDIA_API_KEY or GROQ_API_KEY.');
        }

        $lastError = null;
        foreach ($providers as $provider) {
            try {
                return $this->request($provider, $messages, $options);
            } catch (\Throwable $e) {
                $lastError = $e;
                Log::warning('LLM provider failed; trying next', [
                    'provider' => $provider['name'],
                    'model' => $provider['model'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw new RuntimeException(
            'All AI providers failed.'.($lastError ? ' Last error: '.$lastError->getMessage() : ''),
            0,
            $lastError,
        );
    }

    /**
     * @return array<int, array{name:string,api_key:string,endpoint:string,model:string}>
     */
    private function providers(bool $wantsVision): array
    {
        $providers = [];

        $nvidiaKey = trim((string) config('services.nvidia.api_key', ''));
        if ($nvidiaKey !== '') {
            $providers[] = [
                'name' => 'nvidia',
                'api_key' => $nvidiaKey,
                'endpoint' => (string) config('services.nvidia.chat_endpoint'),
                'model' => $wantsVision
                    ? (string) config('services.nvidia.vision_model')
                    : (string) config('services.nvidia.text_model'),
            ];
        }

        $groqKey = trim((string) config('services.groq.api_key', ''));
        if ($groqKey !== '') {
            $providers[] = [
                'name' => 'groq',
                'api_key' => $groqKey,
                'endpoint' => (string) config('services.groq.chat_endpoint'),
                'model' => $wantsVision
                    ? (string) config('services.groq.vision_model')
                    : (string) config('services.groq.text_model'),
            ];
        }

        return $providers;
    }

    /**
     * @param  array{name:string,api_key:string,endpoint:string,model:string}  $provider
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options
     */
    private function request(array $provider, array $messages, array $options): string
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

        Log::info('LLM chat response received', [
            'provider' => $provider['name'],
            'model' => $provider['model'],
        ]);

        return $content;
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
