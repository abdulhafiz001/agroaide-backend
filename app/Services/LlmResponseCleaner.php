<?php

namespace App\Services;

/**
 * Removes model "thinking" / reasoning scaffolding before anything reaches farmers.
 */
class LlmResponseCleaner
{
    public function clean(string $text): string
    {
        $cleaned = $text;

        $patterns = [
            '/<think\b[^>]*>.*?<\/think>/si',
            '/<thinking\b[^>]*>.*?<\/thinking>/si',
            '/<reasoning\b[^>]*>.*?<\/reasoning>/si',
            '/```(?:thinking|reasoning|thought)\b.*?```/si',
        ];
        foreach ($patterns as $pattern) {
            $cleaned = preg_replace($pattern, '', $cleaned) ?? $cleaned;
        }

        // Common prose prefixes some models emit before the real answer.
        $cleaned = preg_replace(
            '/^(?:thinking|reasoning|analysis|internal monologue)\s*:\s*.*?(?:\n\n|\r\n\r\n)/is',
            '',
            ltrim($cleaned),
        ) ?? $cleaned;

        return trim(preg_replace("/\n{3,}/", "\n\n", $cleaned) ?? $cleaned);
    }
}
