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

        // Gemini / Qwen often emit "Here's a thinking process:" essays before the answer.
        if (preg_match('/here(?:\'s| is) a thinking process\s*:/i', $cleaned)) {
            $parts = preg_split('/here(?:\'s| is) a thinking process\s*:/i', $cleaned, 2);
            $after = $parts[1] ?? '';
            // Keep only the farmer-facing tail after the analysis checklist, if present.
            if (preg_match('/(?:final answer|farmer[- ]facing|output|summary|guide)\s*[:\-]\s*(.+)$/is', $after, $match)) {
                $cleaned = trim($match[1]);
            } elseif (preg_match('/(?:^|\n)((?:[A-Z].{20,}[.!?]\s*){2,})$/s', trim($after), $match)) {
                $cleaned = trim($match[1]);
            } else {
                // Drop the thinking preamble; if nothing useful remains, caller can fall back.
                $cleaned = '';
            }
        }

        $cleaned = preg_replace(
            '/^(?:thinking|reasoning|analysis|internal monologue|analyze user input)\s*:\s*.*?(?:\n\n|\r\n\r\n)/is',
            '',
            ltrim($cleaned),
        ) ?? $cleaned;

        // Strip leftover checklist-style analysis headers.
        $cleaned = preg_replace('/^\*\*\s*analyze[^*\n]+\*\*\s*:?.*/im', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/^- \*\*[^*]+\*\*.*$/im', '', $cleaned) ?? $cleaned;

        return trim(preg_replace("/\n{3,}/", "\n\n", $cleaned) ?? $cleaned);
    }
}
