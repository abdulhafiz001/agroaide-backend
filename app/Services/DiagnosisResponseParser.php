<?php

namespace App\Services;

/**
 * Turns noisy vision-model text into the scan analysis payload the app expects.
 */
class DiagnosisResponseParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $raw): array
    {
        $candidates = $this->jsonCandidates($raw);
        foreach ($candidates as $candidate) {
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $this->normalize($decoded, $raw);
            }
        }

        return $this->normalize($this->fromProse($raw), $raw);
    }

    /**
     * @return list<string>
     */
    public function jsonCandidates(string $raw): array
    {
        $trimmed = trim($raw);
        $trimmed = preg_replace('/^```(?:json)?\s*/i', '', $trimmed) ?? $trimmed;
        $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;
        $trimmed = trim($trimmed);

        $candidates = [];
        if ($trimmed !== '') {
            $candidates[] = $trimmed;
        }

        if (preg_match('/\{.*\}/s', $raw, $match) === 1) {
            $candidates[] = $match[0];
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    public function normalize(array $parsed, string $rawFallback = ''): array
    {
        $condition = $this->normalizeCondition((string) ($parsed['condition'] ?? 'unknown'));
        $summary = trim((string) ($parsed['summary'] ?? ''));
        if ($summary === '') {
            $summary = 'Crop analysis completed. Review the recommendations below.';
        }

        $confidence = $this->normalizeConfidence(
            $parsed['confidencePercent'] ?? $parsed['confidence'] ?? 50,
        );

        $disease = $parsed['disease'] ?? null;
        if (is_string($disease)) {
            $name = trim($disease);
            $disease = ($name === '' || strcasecmp($name, 'none') === 0 || strcasecmp($name, 'null') === 0)
                ? null
                : [
                    'name' => $name,
                    'scientificName' => '',
                    'symptoms' => [],
                    'cause' => 'See summary for details.',
                    'severity' => 'moderate',
                    'spreadRisk' => 'medium',
                ];
        } elseif (is_array($disease)) {
            $name = trim((string) ($disease['name'] ?? ''));
            if ($name === '' || strcasecmp($name, 'none') === 0) {
                $disease = null;
            } else {
                $disease = [
                    'name' => $name,
                    'scientificName' => (string) ($disease['scientificName'] ?? ''),
                    'symptoms' => array_values(array_filter(array_map('strval', $disease['symptoms'] ?? []))),
                    'cause' => (string) ($disease['cause'] ?? 'See summary for details.'),
                    'severity' => $this->normalizeSeverity((string) ($disease['severity'] ?? 'moderate')),
                    'spreadRisk' => $this->normalizeRisk((string) ($disease['spreadRisk'] ?? 'medium')),
                ];
            }
        } else {
            $disease = null;
        }

        if (in_array($condition, ['healthy', 'good'], true)) {
            $disease = null;
        }

        $recommendations = $parsed['recommendations'] ?? [];
        if (! is_array($recommendations)) {
            $recommendations = [];
        }
        $immediate = $this->stringList($recommendations['immediate'] ?? []);
        if ($immediate === []) {
            $immediate = $condition === 'healthy' || $condition === 'good'
                ? ['Continue regular monitoring', 'Maintain current watering and nutrient schedule']
                : ['Take a clearer close-up of affected leaves', 'Consult a local extension officer if symptoms worsen'];
        }

        $details = $parsed['details'] ?? null;
        if (is_string($details) && trim($details) !== '') {
            $details = [
                'plantsVisible' => trim($details),
                'growthStage' => 'unknown',
                'overallVigor' => $condition,
            ];
        } elseif (! is_array($details)) {
            $details = null;
        } else {
            $details = [
                'plantsVisible' => (string) ($details['plantsVisible'] ?? ''),
                'growthStage' => (string) ($details['growthStage'] ?? 'unknown'),
                'overallVigor' => (string) ($details['overallVigor'] ?? $condition),
            ];
        }

        $crop = $parsed['crop'] ?? null;
        if (is_array($crop)) {
            $crop = $crop['name'] ?? $crop['label'] ?? null;
        }
        $crop = is_string($crop) ? trim($crop) : null;
        if ($crop === '') {
            $crop = null;
        }

        return [
            'crop' => $crop,
            'condition' => $condition,
            'conditionLabel' => (string) ($parsed['conditionLabel'] ?? ucfirst($condition)),
            'confidencePercent' => $confidence,
            'summary' => $summary,
            'details' => $details,
            'disease' => $disease,
            'recommendations' => [
                'immediate' => $immediate,
                'products' => $this->productList($recommendations['products'] ?? []),
                'prevention' => $this->stringList($recommendations['prevention'] ?? ['Scout the field weekly for early symptoms']),
                'longTerm' => $this->stringList($recommendations['longTerm'] ?? []),
            ],
            'personalizedNote' => trim((string) ($parsed['personalizedNote'] ?? $summary)),
        ];
    }

    private function normalizeConfidence(mixed $value): int
    {
        if (is_string($value)) {
            $value = trim($value);
            if (str_ends_with($value, '%')) {
                $value = rtrim($value, '%');
            }
        }

        $number = (float) $value;
        if ($number >= 0 && $number <= 1) {
            $number *= 100;
        }

        return max(0, min(100, (int) round($number)));
    }

    /**
     * @return array<string, mixed>
     */
    public function fromProse(string $raw): array
    {
        $field = function (string $label) use ($raw): ?string {
            $pattern = '/\*{0,2}\s*'.preg_quote($label, '/').'\s*\*{0,2}\s*[:\-]\s*(.+)$/im';
            if (preg_match($pattern, $raw, $match) !== 1) {
                return null;
            }

            return trim($match[1], " \t*");
        };

        $condition = $field('Condition') ?? 'unknown';
        $summary = $field('Summary') ?? '';
        if ($summary === '' && preg_match('/^[^\n*]{20,}/', trim($raw), $lead) === 1) {
            $summary = trim($lead[0]);
        }

        $confidenceRaw = $field('Confidence Percent') ?? $field('Confidence') ?? '50';
        $diseaseRaw = $field('Disease');
        $detailsRaw = $field('Details');
        $recsRaw = $field('Recommendations');
        $crop = $field('Crop');

        $parsed = [
            'crop' => $crop,
            'condition' => $condition,
            'confidencePercent' => $confidenceRaw,
            'summary' => $summary !== '' ? $summary : 'Analysis completed from the crop image.',
            'details' => $detailsRaw,
            'disease' => $diseaseRaw,
            'recommendations' => [
                'immediate' => $recsRaw ? [$recsRaw] : [],
                'products' => [],
                'prevention' => [],
                'longTerm' => [],
            ],
            'personalizedNote' => $summary,
        ];

        return $parsed;
    }

    private function normalizeCondition(string $condition): string
    {
        $condition = strtolower(trim($condition));
        $map = [
            'healthy' => 'healthy',
            'good' => 'good',
            'great' => 'good',
            'okay' => 'fair',
            'fair' => 'fair',
            'moderate' => 'fair',
            'poor' => 'poor',
            'bad' => 'poor',
            'diseased' => 'diseased',
            'infected' => 'diseased',
            'critical' => 'critical',
            'severe' => 'critical',
            'unknown' => 'unknown',
        ];

        return $map[$condition] ?? 'unknown';
    }

    private function normalizeSeverity(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['mild', 'moderate', 'severe'], true) ? $value : 'moderate';
    }

    private function normalizeRisk(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['low', 'medium', 'high'], true) ? $value : 'medium';
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (is_string($value) && trim($value) !== '') {
            return [trim($value)];
        }
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($item) => is_scalar($item) ? trim((string) $item) : '',
            $value,
        )));
    }

    /**
     * @param  mixed  $value
     * @return list<array{name:string,type:string,usage:string}>
     */
    private function productList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $products = [];
        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $products[] = ['name' => trim($item), 'type' => 'other', 'usage' => 'Follow local label directions.'];
                continue;
            }
            if (! is_array($item) || trim((string) ($item['name'] ?? '')) === '') {
                continue;
            }
            $type = strtolower((string) ($item['type'] ?? 'other'));
            if (! in_array($type, ['fungicide', 'pesticide', 'fertilizer', 'other'], true)) {
                $type = 'other';
            }
            $products[] = [
                'name' => trim((string) $item['name']),
                'type' => $type,
                'usage' => (string) ($item['usage'] ?? 'Follow local label directions.'),
            ];
        }

        return $products;
    }
}
