<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TranslationService
{
    private string $apiKey;
    private string $model;
    private string $endpoint = 'https://openrouter.ai/api/v1/chat/completions';

    private const LANGUAGE_NAMES = [
        'en' => 'English',
        'ha' => 'Hausa',
        'yo' => 'Yoruba',
        'pcm' => 'Nigerian Pidgin',
    ];

    public function __construct()
    {
        $this->apiKey = trim(config('services.openrouter.api_key') ?? env('OPENROUTER_API_KEY', ''));
        $this->model = trim(config('services.translation.model') ?? env('TRANSLATION_MODEL', 'mistralai/mistral-nemo'));
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
            $response = Http::timeout(20)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
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
                $content = preg_replace('/<think>.*?<\/think>/s', '', $content);
                return trim($content);
            }

            return $text;
        } catch (\Exception $e) {
            Log::warning('Translation failed', ['error' => $e->getMessage()]);
            return $text;
        }
    }
}
