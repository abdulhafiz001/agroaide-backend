<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MarketEyeClient
{
    public function baseUrl(): string
    {
        return rtrim((string) config('marketeye.base_url'), '/');
    }

    public function apiKey(): string
    {
        return trim((string) config('marketeye.api_key', ''));
    }

    public function isConfigured(): bool
    {
        return $this->apiKey() !== '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listMarkets(): array
    {
        $ttl = (int) config('marketeye.markets_cache_ttl', 86400);

        return Cache::remember('marketeye.markets', $ttl, function () {
            $json = $this->get('/markets');
            $markets = $json['data']['markets'] ?? [];

            return is_array($markets) ? array_values($markets) : [];
        });
    }

    /**
     * @return array{market: array<string, mixed>, prices: list<array<string, mixed>>}
     */
    public function marketPrices(int $marketId, ?string $search = null): array
    {
        $path = '/markets/'.$marketId.'/prices';
        if ($search) {
            $path .= '?search='.urlencode($search);
        }

        $json = $this->get($path);

        return [
            'market' => is_array($json['data']['market'] ?? null) ? $json['data']['market'] : [],
            'prices' => is_array($json['data']['prices'] ?? null) ? array_values($json['data']['prices']) : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $path): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('MARKETEYE_API_KEY is not configured.');
        }

        $url = $this->baseUrl().$path;

        try {
            $response = Http::timeout(20)
                ->connectTimeout(8)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-API-Key' => $this->apiKey(),
                ])
                ->get($url);

            if (! $response->successful()) {
                Log::warning('Market Eye request failed', [
                    'status' => $response->status(),
                    'path' => $path,
                    'body' => substr($response->body(), 0, 300),
                ]);

                throw new \RuntimeException('Market Eye HTTP '.$response->status());
            }

            $json = $response->json();
            if (! is_array($json) || empty($json['success'])) {
                throw new \RuntimeException($json['message'] ?? 'Market Eye response unsuccessful');
            }

            return $json;
        } catch (\Throwable $e) {
            Log::warning('Market Eye client error', ['message' => $e->getMessage(), 'path' => $path]);
            throw $e;
        }
    }
}
