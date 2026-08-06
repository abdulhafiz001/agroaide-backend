<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class KindwiseCropHealthClient
{
    public function isConfigured(): bool
    {
        return trim((string) config('services.kindwise.api_key')) !== '';
    }

    /**
     * Identify crop + disease/pest from a base64 image (with or without data-URL prefix).
     *
     * @param  array{latitude?:float,longitude?:float,language?:string}  $options
     * @return array{
     *   access_token:?string,
     *   raw:array,
     *   crop:?array,
     *   disease:?array,
     *   is_healthy:bool,
     *   is_crop:bool,
     *   confidence:float
     * }
     */
    public function identify(string $imageBase64OrDataUrl, array $options = []): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('kindwise_not_configured');
        }

        $image = $this->normalizeBase64($imageBase64OrDataUrl);
        $language = $this->mapLanguage((string) ($options['language'] ?? 'en'));
        $details = implode(',', [
            'common_names', 'type', 'description', 'wiki_description',
            'treatment', 'symptoms', 'severity', 'spreading', 'taxonomy',
        ]);

        $query = http_build_query([
            'details' => $details,
            'language' => $language,
        ]);

        $payload = ['images' => [$image]];
        if (isset($options['latitude'], $options['longitude'])) {
            $payload['latitude'] = (float) $options['latitude'];
            $payload['longitude'] = (float) $options['longitude'];
        }

        $url = rtrim((string) config('services.kindwise.base_url'), '/').'/identification?'.$query;

        try {
            $response = Http::timeout(90)
                ->connectTimeout(15)
                ->withHeaders([
                    'Api-Key' => (string) config('services.kindwise.api_key'),
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException('kindwise_connection_failed: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            Log::warning('Kindwise identification failed', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 400),
            ]);
            throw new RuntimeException(
                'kindwise_http_'.$response->status().': '.
                (string) data_get($response->json(), 'error', 'provider_error'),
            );
        }

        $raw = $response->json();
        if (! is_array($raw)) {
            throw new RuntimeException('kindwise_invalid_response');
        }

        $crop = $this->topSuggestion(data_get($raw, 'result.crop.suggestions'));
        $disease = $this->topSuggestion(data_get($raw, 'result.disease.suggestions'));
        $isCrop = $this->looksLikeCrop($raw, $crop, $disease);
        $isHealthy = $isCrop && $this->looksHealthy($disease);
        $confidence = $isCrop
            ? max((float) ($crop['probability'] ?? 0), (float) ($disease['probability'] ?? 0))
            : max(0.05, (float) ($crop['probability'] ?? 0));

        return [
            'access_token' => data_get($raw, 'access_token'),
            'raw' => $raw,
            'crop' => $isCrop ? $crop : null,
            'disease' => ($isCrop && ! $isHealthy) ? $disease : null,
            'is_healthy' => $isHealthy,
            'is_crop' => $isCrop,
            'confidence' => $confidence > 0 ? $confidence : 0.05,
        ];
    }

    private function normalizeBase64(string $input): string
    {
        if (str_starts_with($input, 'data:')) {
            $parts = explode(',', $input, 2);

            return preg_replace('/\s+/', '', $parts[1] ?? '') ?? '';
        }

        return preg_replace('/\s+/', '', $input) ?? '';
    }

    private function mapLanguage(string $lang): string
    {
        return match ($lang) {
            'ha', 'yo', 'pcm', 'en' => 'en',
            default => 'en',
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function topSuggestion(mixed $suggestions): ?array
    {
        if (! is_array($suggestions) || $suggestions === []) {
            return null;
        }

        usort($suggestions, static fn ($a, $b) => ((float) ($b['probability'] ?? 0)) <=> ((float) ($a['probability'] ?? 0)));
        $top = $suggestions[0] ?? null;

        return is_array($top) ? $top : null;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>|null  $crop
     * @param  array<string, mixed>|null  $disease
     */
    private function looksLikeCrop(array $raw, ?array $crop, ?array $disease): bool
    {
        // plant.id-style flag when present on crop.health responses
        $isPlantBinary = data_get($raw, 'result.is_plant.binary');
        if ($isPlantBinary === false || $isPlantBinary === 0 || $isPlantBinary === 'false') {
            return false;
        }
        $isPlantProb = data_get($raw, 'result.is_plant.probability');
        if (is_numeric($isPlantProb) && (float) $isPlantProb < 0.35) {
            return false;
        }

        $cropProb = (float) ($crop['probability'] ?? 0);
        $diseaseProb = (float) ($disease['probability'] ?? 0);

        if ($crop === null && $disease === null) {
            return false;
        }

        // Random photos often still get a weak top suggestion — require a real signal.
        if ($cropProb < 0.28 && $diseaseProb < 0.35) {
            return false;
        }

        return true;
    }

    private function looksHealthy(?array $disease): bool
    {
        if ($disease === null) {
            return true;
        }

        $name = strtolower((string) ($disease['name'] ?? ''));
        $type = strtolower((string) data_get($disease, 'details.type', data_get($disease, 'type', '')));

        return str_contains($name, 'healthy')
            || str_contains($name, 'no disease')
            || $type === 'healthy'
            || ((float) ($disease['probability'] ?? 0) < 0.15 && str_contains($name, 'abiotic'));
    }
}
