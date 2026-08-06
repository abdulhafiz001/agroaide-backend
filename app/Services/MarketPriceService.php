<?php

namespace App\Services;

use App\Models\FarmField;
use App\Models\MarketPriceHistory;
use App\Models\MarketPriceSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MarketPriceService
{
    public function __construct(private MarketEyeClient $client) {}

    /**
     * @return list<string>
     */
    public function userCrops(User $user): array
    {
        $profile = is_array($user->crops) ? $user->crops : [];
        $fields = FarmField::query()
            ->where('user_id', $user->id)
            ->whereNotNull('crop')
            ->pluck('crop')
            ->all();

        $merged = [];
        foreach (array_merge($profile, $fields) as $crop) {
            $name = trim((string) $crop);
            if ($name === '') {
                continue;
            }
            $key = $this->normalizeCropKey($name);
            $merged[$key] = $key;
        }

        return array_values($merged);
    }

    public function normalizeCropKey(string $crop): string
    {
        $trimmed = trim($crop);
        if ($trimmed === '') {
            return 'Unknown';
        }

        $aliases = config('marketeye.crop_aliases', []);
        foreach (array_keys($aliases) as $known) {
            if (strcasecmp((string) $known, $trimmed) === 0) {
                return (string) $known;
            }
        }

        // Title-case single word crops
        return ucwords(strtolower($trimmed));
    }

    /**
     * @return array{id:int,name:string,area:?string,city:?string,state:?string,lat:?float,lng:?float,distanceKm:?float}
     */
    public function nearestMarket(?float $lat, ?float $lng, ?string $farmLocation = null): array
    {
        $markets = $this->client->isConfigured() ? $this->client->listMarkets() : [];
        if ($markets === []) {
            return $this->defaultMarket();
        }

        $enriched = [];
        foreach ($markets as $m) {
            $id = (int) ($m['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $coords = $this->coordsForMarket($id, $m);
            $enriched[] = [
                'id' => $id,
                'name' => (string) ($m['name'] ?? 'Market'),
                'area' => $m['area'] ?? null,
                'city' => $m['city'] ?? null,
                'state' => $m['state'] ?? null,
                'lat' => $coords['lat'],
                'lng' => $coords['lng'],
            ];
        }

        if ($enriched === []) {
            return $this->defaultMarket();
        }

        // Prefer haversine when user has GPS.
        if ($lat !== null && $lng !== null) {
            $best = null;
            $bestDist = PHP_FLOAT_MAX;
            foreach ($enriched as $m) {
                if ($m['lat'] === null || $m['lng'] === null) {
                    continue;
                }
                $d = $this->haversineKm($lat, $lng, (float) $m['lat'], (float) $m['lng']);
                if ($d < $bestDist) {
                    $bestDist = $d;
                    $best = $m;
                }
            }
            if ($best) {
                $best['distanceKm'] = round($bestDist, 1);

                return $best;
            }
        }

        // Match by city / state text from farm_location.
        $hay = strtolower((string) $farmLocation);
        if ($hay !== '') {
            foreach ($enriched as $m) {
                $city = strtolower((string) ($m['city'] ?? ''));
                $state = strtolower((string) ($m['state'] ?? ''));
                $area = strtolower((string) ($m['area'] ?? ''));
                if (($city && str_contains($hay, $city))
                    || ($state && str_contains($hay, $state))
                    || ($area && str_contains($hay, $area))) {
                    $m['distanceKm'] = null;

                    return $m;
                }
            }
        }

        $first = $enriched[0];
        $first['distanceKm'] = null;

        return $first;
    }

    /**
     * Sync prices for one market against a crop list.
     *
     * @param  list<string>  $cropKeys
     */
    public function syncMarket(int $marketId, array $cropKeys, array $marketMeta = []): void
    {
        if (! $this->client->isConfigured() || $cropKeys === []) {
            return;
        }

        $payload = $this->client->marketPrices($marketId);
        $meMarket = $payload['market'];
        $prices = $payload['prices'];

        $name = (string) ($marketMeta['name'] ?? $meMarket['name'] ?? 'Market');
        $area = $marketMeta['area'] ?? $meMarket['area'] ?? null;
        $city = $marketMeta['city'] ?? $meMarket['city'] ?? null;
        $state = $marketMeta['state'] ?? $meMarket['state'] ?? null;
        $coords = $this->coordsForMarket($marketId, array_merge($meMarket, $marketMeta));

        $today = now()->toDateString();

        foreach ($cropKeys as $cropKey) {
            $match = $this->matchProduct($cropKey, $prices);

            if (! $match) {
                MarketPriceSnapshot::query()->updateOrCreate(
                    ['market_id' => $marketId, 'crop_key' => $cropKey],
                    [
                        'market_name' => $name,
                        'market_area' => $area,
                        'market_city' => $city,
                        'market_state' => $state,
                        'market_lat' => $coords['lat'],
                        'market_lng' => $coords['lng'],
                        'product_id' => null,
                        'product_name' => null,
                        'product_slug' => null,
                        'unit' => null,
                        'price_avg' => null,
                        'price_min' => null,
                        'price_max' => null,
                        'currency' => 'NGN',
                        'confidence_level' => null,
                        'available' => false,
                        'source_updated_on' => null,
                        'fetched_at' => now(),
                    ]
                );

                continue;
            }

            $avg = (float) ($match['price']['avg'] ?? 0);
            $unit = (string) ($match['measurement']['unit'] ?? $match['product']['unit'] ?? '');
            $productId = (int) ($match['product']['id'] ?? 0) ?: null;
            $sourceDate = $match['updated_at'] ?? $today;

            MarketPriceSnapshot::query()->updateOrCreate(
                ['market_id' => $marketId, 'crop_key' => $cropKey],
                [
                    'market_name' => $name,
                    'market_area' => $area,
                    'market_city' => $city,
                    'market_state' => $state,
                    'market_lat' => $coords['lat'],
                    'market_lng' => $coords['lng'],
                    'product_id' => $productId,
                    'product_name' => $match['product']['name'] ?? null,
                    'product_slug' => $match['product']['slug'] ?? null,
                    'unit' => $unit,
                    'price_avg' => $avg,
                    'price_min' => $match['price']['min'] ?? $avg,
                    'price_max' => $match['price']['max'] ?? $avg,
                    'currency' => $match['price']['currency'] ?? 'NGN',
                    'confidence_level' => $match['quality']['confidence_level'] ?? null,
                    'available' => true,
                    'source_updated_on' => $sourceDate,
                    'fetched_at' => now(),
                ]
            );

            $existing = MarketPriceHistory::query()
                ->where('market_id', $marketId)
                ->where('crop_key', $cropKey)
                ->where('recorded_on', $today)
                ->first();

            if (! $existing) {
                $previous = MarketPriceHistory::query()
                    ->where('market_id', $marketId)
                    ->where('crop_key', $cropKey)
                    ->orderByDesc('recorded_on')
                    ->first();

                // Skip new history row if price unchanged vs last recorded day.
                if ($previous && abs((float) $previous->price_avg - $avg) < 0.01) {
                    // Still touch today's row only when price changed — per spec save on change.
                    // If unchanged across days, keep last point (chart stays flat via last known).
                    continue;
                }

                MarketPriceHistory::query()->create([
                    'market_id' => $marketId,
                    'crop_key' => $cropKey,
                    'product_id' => $productId,
                    'unit' => $unit,
                    'price_avg' => $avg,
                    'currency' => $match['price']['currency'] ?? 'NGN',
                    'recorded_on' => $today,
                ]);
            } elseif (abs((float) $existing->price_avg - $avg) >= 0.01) {
                $existing->update([
                    'price_avg' => $avg,
                    'unit' => $unit,
                    'product_id' => $productId,
                ]);
            }
        }
    }

    /**
     * Sync all markets needed by all users (daily job).
     */
    public function syncAllUsers(): int
    {
        if (! $this->client->isConfigured()) {
            Log::warning('Market price sync skipped: MARKETEYE_API_KEY missing');

            return 0;
        }

        $groups = []; // marketId => ['meta' => ..., 'crops' => set]

        User::query()->select(['id', 'crops', 'farm_latitude', 'farm_longitude', 'farm_location'])->chunkById(100, function ($users) use (&$groups) {
            foreach ($users as $user) {
                $crops = $this->userCrops($user);
                if ($crops === []) {
                    continue;
                }
                $lat = $user->farm_latitude !== null ? (float) $user->farm_latitude : null;
                $lng = $user->farm_longitude !== null ? (float) $user->farm_longitude : null;
                $market = $this->nearestMarket($lat, $lng, $user->farm_location);
                $mid = (int) $market['id'];
                if (! isset($groups[$mid])) {
                    $groups[$mid] = ['meta' => $market, 'crops' => []];
                }
                foreach ($crops as $c) {
                    $groups[$mid]['crops'][$c] = $c;
                }
            }
        });

        $synced = 0;
        foreach ($groups as $marketId => $group) {
            try {
                $this->syncMarket($marketId, array_values($group['crops']), $group['meta']);
                $synced++;
            } catch (\Throwable $e) {
                Log::error('Market sync failed', ['market_id' => $marketId, 'message' => $e->getMessage()]);
            }
        }

        return $synced;
    }

    /**
     * Ensure user's nearest market has fresh-enough data; lazy sync if stale/missing.
     */
    public function ensureUserMarketFresh(User $user): array
    {
        $crops = $this->userCrops($user);
        $lat = $user->farm_latitude !== null ? (float) $user->farm_latitude : null;
        $lng = $user->farm_longitude !== null ? (float) $user->farm_longitude : null;
        $market = $this->nearestMarket($lat, $lng, $user->farm_location);

        if ($crops === [] || ! $this->client->isConfigured()) {
            return ['market' => $market, 'crops' => $crops];
        }

        $marketId = (int) $market['id'];
        $stale = MarketPriceSnapshot::query()
            ->where('market_id', $marketId)
            ->whereIn('crop_key', $crops)
            ->where('fetched_at', '>=', now()->subHours(20))
            ->count() < count($crops);

        if ($stale) {
            $lockKey = 'marketeye.sync.'.$marketId;
            $lockTtl = (int) config('marketeye.sync_lock_seconds', 120);
            if (Cache::add($lockKey, 1, $lockTtl)) {
                try {
                    $this->syncMarket($marketId, $crops, $market);
                } catch (\Throwable $e) {
                    Log::warning('Lazy market sync failed', ['message' => $e->getMessage()]);
                } finally {
                    Cache::forget($lockKey);
                }
            }
        }

        return ['market' => $market, 'crops' => $crops];
    }

    /**
     * Build intel payload for the app.
     *
     * @return array<string, mixed>
     */
    public function intelForUser(User $user, ?string $cropFilter = null): array
    {
        $ensured = $this->ensureUserMarketFresh($user);
        $market = $ensured['market'];
        $crops = $ensured['crops'];
        if ($cropFilter) {
            $key = $this->normalizeCropKey($cropFilter);
            $crops = in_array($key, $crops, true) ? [$key] : [$key];
        }

        $marketId = (int) $market['id'];
        $snapshots = MarketPriceSnapshot::query()
            ->where('market_id', $marketId)
            ->whereIn('crop_key', $crops ?: ['__none__'])
            ->get()
            ->keyBy('crop_key');

        $historyRows = MarketPriceHistory::query()
            ->where('market_id', $marketId)
            ->whereIn('crop_key', $crops ?: ['__none__'])
            ->orderBy('recorded_on')
            ->get();

        $history = [];
        foreach ($historyRows as $row) {
            $history[$row->crop_key][] = [
                'date' => $row->recorded_on?->toDateString(),
                'price' => (float) $row->price_avg,
            ];
        }

        // If only one history point, prepend a synthetic previous from snapshot for chart seed — skip; rising needs 2 real points.
        $prices = [];
        $lastSynced = null;

        foreach ($crops as $cropKey) {
            /** @var MarketPriceSnapshot|null $snap */
            $snap = $snapshots->get($cropKey);
            $series = $history[$cropKey] ?? [];
            $trend = 'stable';
            $changePercent = null;

            if (count($series) >= 2) {
                $prev = $series[count($series) - 2]['price'];
                $curr = $series[count($series) - 1]['price'];
                if ($prev > 0) {
                    $changePercent = round((($curr - $prev) / $prev) * 100, 1);
                    $trend = $changePercent > 0.5 ? 'up' : ($changePercent < -0.5 ? 'down' : 'stable');
                }
            } elseif ($snap && $snap->available && count($series) === 1) {
                // Compare to any older history if we skipped unchanged days — already in series.
                $trend = 'stable';
            }

            $location = trim(implode(', ', array_filter([
                $market['name'] ?? $snap?->market_name,
                $market['city'] ?? $snap?->market_city,
            ])));

            if ($snap?->fetched_at && ($lastSynced === null || $snap->fetched_at->gt($lastSynced))) {
                $lastSynced = $snap->fetched_at;
            }

            $prices[] = [
                'commodity' => $cropKey,
                'productName' => $snap?->product_name,
                'price' => $snap && $snap->available ? (float) $snap->price_avg : null,
                'pricePerTon' => $snap && $snap->available ? (float) $snap->price_avg : null, // legacy field for old UI
                'unit' => $snap?->unit,
                'currency' => $snap?->currency ?? 'NGN',
                'location' => $location !== '' ? $location : ($market['name'] ?? 'Nearest market'),
                'trend' => $trend,
                'changePercent' => $changePercent,
                'available' => (bool) ($snap?->available),
                'confidence' => $snap?->confidence_level,
            ];
        }

        return [
            'market' => [
                'id' => $marketId,
                'name' => $market['name'] ?? 'Market',
                'area' => $market['area'] ?? null,
                'city' => $market['city'] ?? null,
                'state' => $market['state'] ?? null,
                'distanceKm' => $market['distanceKm'] ?? null,
            ],
            'marketPrices' => $prices,
            'history' => $history,
            'highlights' => $this->buildHighlights($prices, $market),
            'lastUpdated' => ($lastSynced ?? now())->toIso8601String(),
            'lastSyncedAt' => ($lastSynced ?? now())->toIso8601String(),
            'source' => 'Market Eye (crowd-verified)',
            'disclaimer' => 'Prices for the nearest Market Eye market to your farm. Guide only — confirm locally before selling.',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $prices
     * @return list<string>
     */
    private function buildHighlights(array $prices, array $market): array
    {
        $name = $market['name'] ?? 'your nearest market';
        $lines = ["Showing crowd-verified prices from {$name}."];
        foreach ($prices as $p) {
            if (! empty($p['available']) && ($p['trend'] ?? '') === 'up' && ($p['changePercent'] ?? 0) > 0) {
                $lines[] = "{$p['commodity']} is rising (~{$p['changePercent']}%).";
            }
            if (! empty($p['available']) && ($p['trend'] ?? '') === 'down' && ($p['changePercent'] ?? 0) < 0) {
                $lines[] = "{$p['commodity']} is falling (~{$p['changePercent']}%).";
            }
        }

        return array_slice($lines, 0, 4);
    }

    /**
     * @param  list<array<string, mixed>>  $prices
     * @return array<string, mixed>|null
     */
    private function matchProduct(string $cropKey, array $prices): ?array
    {
        $aliases = config("marketeye.crop_aliases.{$cropKey}", [strtolower($cropKey)]);
        $aliases = array_map('strtolower', $aliases);

        $best = null;
        $bestScore = -1;

        foreach ($prices as $row) {
            $pname = strtolower((string) ($row['product']['name'] ?? ''));
            if ($pname === '') {
                continue;
            }
            foreach ($aliases as $i => $alias) {
                $score = 0;
                if ($pname === $alias) {
                    $score = 100 - $i;
                } elseif (str_starts_with($pname, $alias)) {
                    $score = 80 - $i;
                } elseif (str_contains($pname, $alias)) {
                    $score = 60 - $i;
                }
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $row;
                }
            }
        }

        return $bestScore >= 60 ? $best : null;
    }

    /**
     * @param  array<string, mixed>  $market
     * @return array{lat:?float,lng:?float}
     */
    private function coordsForMarket(int $id, array $market): array
    {
        $lat = isset($market['lat']) && $market['lat'] !== null ? (float) $market['lat'] : null;
        $lng = isset($market['lng']) && $market['lng'] !== null ? (float) $market['lng'] : null;
        if ($lat !== null && $lng !== null) {
            return ['lat' => $lat, 'lng' => $lng];
        }

        $fallback = config("marketeye.market_coords.{$id}");
        if (is_array($fallback)) {
            return [
                'lat' => (float) $fallback['lat'],
                'lng' => (float) $fallback['lng'],
            ];
        }

        return ['lat' => null, 'lng' => null];
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $earth * asin(min(1, sqrt($a)));
    }

    /**
     * @return array{id:int,name:string,area:?string,city:?string,state:?string,lat:?float,lng:?float,distanceKm:?float}
     */
    private function defaultMarket(): array
    {
        $coords = config('marketeye.market_coords.1', ['lat' => 9.0765, 'lng' => 7.3986]);

        return [
            'id' => 1,
            'name' => 'Wuse Market',
            'area' => 'Wuse',
            'city' => 'Abuja',
            'state' => 'FCT',
            'lat' => (float) $coords['lat'],
            'lng' => (float) $coords['lng'],
            'distanceKm' => null,
        ];
    }
}
