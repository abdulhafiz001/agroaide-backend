<?php

namespace App\Services;

/**
 * Removes model "thinking" / reasoning scaffolding before anything reaches farmers.
 */
class LlmResponseCleaner
{
    /**
     * @var list<string>
     */
    private const REASONING_NEEDLES = [
        '<think',
        '</think>',
        '<thinking',
        '<reasoning',
        'thinking process',
        'deconstruct the request',
        'analyze user input',
        "here's a thinking",
        'here is a thinking',
        'internal monologue',
        'chain of thought',
        'step-by-step reasoning',
        'kind=',
        'bestdate=',
        '**goal:**',
        '**language:**',
        'farmer-facing sentences only',
    ];

    public function clean(string $text): string
    {
        $cleaned = $this->stripReasoningBlocks($text);
        $cleaned = $this->stripThinkingPreamble($cleaned);
        $cleaned = $this->stripAnalysisBullets($cleaned);
        $cleaned = $this->stripInternalMetadata($cleaned);
        $cleaned = $this->normalizeDashes($cleaned);
        $cleaned = trim(preg_replace("/\n{3,}/", "\n\n", $cleaned) ?? $cleaned);

        if ($cleaned === '' || $this->looksLikeReasoning($cleaned)) {
            return '';
        }

        return $cleaned;
    }

    public function farmerFacing(string $text, string $fallback = ''): string
    {
        $cleaned = $this->clean($text);
        if ($cleaned === '') {
            $cleaned = $this->normalizeDashes($this->stripInternalMetadata($fallback));
        }

        return $cleaned;
    }

    public function stripInternalMetadata(string $text): string
    {
        $cleaned = preg_replace('/\[\s*harvest[-_]window(?::[^\]]*)?\]/i', '', $text) ?? $text;
        $cleaned = preg_replace('/harvest[-_]window:fieldId?=\d+/i', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\[fieldId=\d+\]/i', '', $cleaned) ?? $cleaned;

        return trim(preg_replace('/\s{2,}/', ' ', $cleaned) ?? $cleaned);
    }

    public function normalizeDashes(string $text): string
    {
        // Replace em-dashes and en-dashes with standard dashes / clean punctuation
        $cleaned = str_replace(["\xE2\x80\x94", "\xE2\x80\x93", '—', '–'], '-', $text);
        $cleaned = preg_replace('/(?<=\s)--(?=\s)/', '-', $cleaned) ?? $cleaned;

        return trim(preg_replace('/\s{2,}/', ' ', $cleaned) ?? $cleaned);
    }

    public function looksLikeReasoning(string $text): bool
    {
        $haystack = strtolower(trim($text));
        if ($haystack === '') {
            return false;
        }

        foreach (self::REASONING_NEEDLES as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return (bool) preg_match('/^\s*\d+\.\s*\*\*(?:deconstruct|goal|language|kind|crop|location)\b/im', $text);
    }

    private function stripReasoningBlocks(string $text): string
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

        // DeepSeek-style dumps often open <think> and never close it.
        $cleaned = preg_replace('/<think\b[^>]*>.*$/si', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/<thinking\b[^>]*>.*$/si', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/<reasoning\b[^>]*>.*$/si', '', $cleaned) ?? $cleaned;

        return $cleaned;
    }

    private function stripThinkingPreamble(string $text): string
    {
        $cleaned = $text;

        if (preg_match('/(?:here(?:\'s| is) a )?thinking process\s*:/i', $cleaned)) {
            $parts = preg_split('/(?:here(?:\'s| is) a )?thinking process\s*:/i', $cleaned, 2);
            $after = $parts[1] ?? '';
            if (preg_match('/(?:final answer|farmer[- ]facing|output|summary|guide)\s*[:\-]\s*(.+)$/is', $after, $match)) {
                $cleaned = trim($match[1]);
            } else {
                $cleaned = $this->lastFarmerSentences($after);
            }
        }

        $cleaned = preg_replace(
            '/^(?:thinking|reasoning|analysis|internal monologue|analyze user input)\s*:\s*.*?(?:\n\n|\r\n\r\n)/is',
            '',
            ltrim($cleaned),
        ) ?? $cleaned;

        return $cleaned;
    }

    private function stripAnalysisBullets(string $text): string
    {
        $cleaned = preg_replace('/^\*\*\s*analyze[^*\n]+\*\*\s*:?.*/im', '', $text) ?? $text;
        $cleaned = preg_replace('/^- \*\*[^*]+\*\*.*$/im', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/^\s*\d+\.\s*\*\*(?:deconstruct|goal|language|kind|crop|location)[^*]*\*\*.*$/im', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/^\s*\*\s+\*\*(?:goal|language|kind|crop|location|contextual note)[^*]*\*\*.*$/im', '', $cleaned) ?? $cleaned;

        return $cleaned;
    }

    private function lastFarmerSentences(string $text): string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return '';
        }

        if (preg_match('/(?:^|\n)((?:[A-Z].{20,}[.!?]\s*){1,3})$/s', $trimmed, $match)) {
            $candidate = trim($match[1]);
            if (! $this->looksLikeReasoning($candidate)) {
                return $candidate;
            }
        }

        return '';
    }
}
