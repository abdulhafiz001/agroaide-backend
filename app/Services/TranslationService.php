<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TranslationService
{
    private string $apiKey;
    private string $model;
    private string $endpoint;
    private string $apiVersion;

    private const LANGUAGE_NAMES = [
        'en' => 'English',
        'ha' => 'Hausa',
        'yo' => 'Yoruba',
        'pcm' => 'Nigerian Pidgin',
    ];

    public function __construct()
    {
        $this->apiKey = trim(config('services.github_models.api_key', ''));
        $this->model = trim(config('services.github_models.model', 'openai/gpt-4o-mini'));
        $this->endpoint = trim(config('services.github_models.endpoint', 'https://models.github.ai/inference/chat/completions'));
        $this->apiVersion = trim(config('services.github_models.api_version', '2022-11-28'));
    }

    public static function languageName(string $code): string
    {
        return self::LANGUAGE_NAMES[$code] ?? 'English';
    }

    public function translate(string $text, string $targetLang): string
    {
        if ($targetLang === 'en' || empty($text)) {
            return $text;
        }

        $langName = self::languageName($targetLang);
        $cacheKey = 'translate_' . md5("{$text}_{$targetLang}");

        return Cache::remember($cacheKey, 3600, function () use ($text, $langName) {
            return $this->callTranslation($text, $langName);
        });
    }

    public function translateBatch(array $texts, string $targetLang): array
    {
        if ($targetLang === 'en') {
            return $texts;
        }

        return array_map(fn (string $t) => $this->translate($t, $targetLang), $texts);
    }

    private function callTranslation(string $text, string $langName): string
    {
        if (empty($this->apiKey)) {
            return $text;
        }

        try {
            Log::info('GitHub Models: translating text', ['target_lang' => $langName, 'model' => $this->model]);

            $response = Http::timeout(20)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => $this->apiVersion,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->endpoint, [
                    'model' => $this->model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => "You are a translator for Nigerian farmers. Translate the following text into {$langName}. Keep it natural, simple, and farmer-friendly. Return ONLY the translated text, nothing else. Do not add quotes or explanations.",
                        ],
                        ['role' => 'user', 'content' => $text],
                    ],
                    'max_tokens' => 512,
                    'temperature' => 0.3,
                ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content') ?? $text;
                Log::info('GitHub Models: translation complete', ['target_lang' => $langName]);
                return trim($content);
            }

            Log::warning('GitHub Models translation failed', ['status' => $response->status(), 'body' => $response->body()]);
            return $text;
        } catch (\Exception $e) {
            Log::warning('Translation failed', ['error' => $e->getMessage()]);
            return $text;
        }
    }
}
