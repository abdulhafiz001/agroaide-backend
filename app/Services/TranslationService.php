<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TranslationService
{
    private const LANGUAGE_NAMES = [
        'en' => 'English',
        'ha' => 'Hausa',
        'yo' => 'Yoruba',
        'pcm' => 'Nigerian Pidgin',
    ];

    public function __construct(private LlmChatClient $llm) {}

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
        $cacheKey = 'translate_'.md5("{$text}_{$targetLang}");

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
        try {
            return $this->llm->chat([
                [
                    'role' => 'system',
                    'content' => "You are a translator for Nigerian farmers. Translate the following text into {$langName}. Keep it natural, simple, and farmer-friendly. Return ONLY the translated text, nothing else. Do not add quotes or explanations.",
                ],
                ['role' => 'user', 'content' => $text],
            ], [
                'timeout' => 20,
                'max_tokens' => 512,
                'temperature' => 0.3,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Translation failed', ['error' => $e->getMessage()]);

            return $text;
        }
    }
}
