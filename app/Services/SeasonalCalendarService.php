<?php

namespace App\Services;

use App\Models\FarmField;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class SeasonalCalendarService
{
    /**
     * Resolve Nigeria AEZ from coordinates or location string heuristics.
     */
    public function resolveZone(?float $lat, ?float $lng = null, ?string $location = null): string
    {
        if ($lat !== null) {
            if ($lat < 7.5) {
                return 'humid_forest';
            }
            if ($lat < 11.0) {
                return 'guinea_savanna';
            }

            return 'sudan_sahel';
        }

        $haystack = strtolower((string) $location);
        $sahelHints = ['kano', 'sokoto', 'katsina', 'maiduguri', 'borno', 'yobe', 'jigawa', 'zamfara'];
        $forestHints = ['lagos', 'ibadan', 'port harcourt', 'calabar', 'benin', 'enugu', 'owerri', 'abeokuta', 'akure'];

        foreach ($sahelHints as $hint) {
            if (str_contains($haystack, $hint)) {
                return 'sudan_sahel';
            }
        }
        foreach ($forestHints as $hint) {
            if (str_contains($haystack, $hint)) {
                return 'humid_forest';
            }
        }

        return 'guinea_savanna';
    }

    /**
     * @return array{zone: string, season: string, isRainy: bool, rainyMonths: array<int>, month: int}
     */
    public function currentSeason(string $zone, CarbonInterface|string|null $date = null): array
    {
        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date ?? now());
        $month = (int) $date->format('n');
        $zones = config('seasonal_crops.zones', []);
        $zoneConfig = $zones[$zone] ?? $zones['guinea_savanna'] ?? [];
        $rainyMonths = $zoneConfig['rainyMonths'] ?? [];
        $isRainy = in_array($month, $rainyMonths, true);

        return [
            'zone' => $zone,
            'season' => $isRainy ? 'rainy' : 'dry',
            'isRainy' => $isRainy,
            'rainyMonths' => $rainyMonths,
            'month' => $month,
        ];
    }

    public function plantingWindowActive(string $crop, string $zone, CarbonInterface|string|null $date = null): bool
    {
        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date ?? now());
        $month = (int) $date->format('n');
        $cropKey = $this->normalizeCropName($crop);
        $crops = config('seasonal_crops.crops', []);
        $config = $crops[$cropKey] ?? null;
        if (! $config) {
            return false;
        }

        $months = $config['plantingMonths'][$zone] ?? [];

        return in_array($month, $months, true);
    }

    /**
     * @return array{
     *   zone: string,
     *   season: array{zone: string, season: string, isRainy: bool, rainyMonths: array<int>, month: int},
     *   suggestions: array<int, array<string, mixed>>
     * }
     */
    public function suggestionsForUser(User $user, ?string $crop = null, ?int $fieldId = null): array
    {
        $lat = $user->farm_latitude !== null ? (float) $user->farm_latitude : null;
        $lng = $user->farm_longitude !== null ? (float) $user->farm_longitude : null;
        $zone = $this->resolveZone($lat, $lng, $user->farm_location);
        $season = $this->currentSeason($zone);
        $today = now();

        $field = null;
        if ($fieldId) {
            $field = FarmField::where('user_id', $user->id)->where('id', $fieldId)->first();
        }

        $cropNames = [];
        if ($crop) {
            $cropNames = [$this->normalizeCropName($crop)];
        } elseif ($field?->crop) {
            $cropNames = [$this->normalizeCropName($field->crop)];
        } else {
            $userCrops = is_array($user->crops) ? $user->crops : [];
            $fieldCrops = $user->farmFields()->pluck('crop')->filter()->all();
            $cropNames = array_values(array_unique(array_map(
                fn ($c) => $this->normalizeCropName((string) $c),
                array_merge($userCrops, $fieldCrops),
            )));
            if ($cropNames === []) {
                $cropNames = array_keys(config('seasonal_crops.crops', []));
            }
        }

        $suggestions = [];
        foreach ($cropNames as $cropName) {
            $crops = config('seasonal_crops.crops', []);
            if (! isset($crops[$cropName])) {
                continue;
            }

            $config = $crops[$cropName];
            $plantingMonths = $config['plantingMonths'][$zone] ?? [];
            $windowActive = $this->plantingWindowActive($cropName, $zone, $today);
            $stages = [];

            $plantedAt = $field?->planted_at;
            if ($plantedAt && $field && $this->normalizeCropName($field->crop) === $cropName) {
                foreach ($config['stageOffsets'] as $stage => $offsetDays) {
                    $due = $plantedAt->copy()->addDays((int) $offsetDays);
                    $stages[] = [
                        'stage' => $stage,
                        'offsetDays' => (int) $offsetDays,
                        'dueDate' => $due->toDateString(),
                        'isDue' => $due->isSameDay($today) || ($due->lte($today) && $due->diffInDays($today) <= 7),
                        'isPast' => $due->lt($today->copy()->startOfDay()),
                    ];
                }
            } else {
                foreach ($config['stageOffsets'] as $stage => $offsetDays) {
                    $stages[] = [
                        'stage' => $stage,
                        'offsetDays' => (int) $offsetDays,
                        'dueDate' => null,
                        'isDue' => false,
                        'isPast' => false,
                    ];
                }
            }

            $suggestions[] = [
                'crop' => $cropName,
                'plantingMonths' => $plantingMonths,
                'plantingWindowActive' => $windowActive,
                'stages' => $stages,
                'fieldId' => $field ? (string) $field->id : null,
            ];
        }

        return [
            'zone' => $zone,
            'zoneLabel' => config("seasonal_crops.zones.{$zone}.label", $zone),
            'season' => $season,
            'suggestions' => $suggestions,
        ];
    }

    public function normalizeCropName(string $crop): string
    {
        $trimmed = trim($crop);
        $crops = config('seasonal_crops.crops', []);
        foreach (array_keys($crops) as $name) {
            if (strcasecmp($name, $trimmed) === 0) {
                return $name;
            }
        }

        return ucfirst(strtolower($trimmed));
    }

    public function isKnownCrop(string $crop): bool
    {
        $key = $this->normalizeCropName($crop);
        $crops = config('seasonal_crops.crops', []);

        return isset($crops[$key]);
    }

    /**
     * True when all planting months for this crop/zone in the current calendar year
     * are strictly before the current month (season finished for this year).
     */
    public function seasonPassedThisYear(string $crop, string $zone, CarbonInterface|string|null $date = null): bool
    {
        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date ?? now());
        $month = (int) $date->format('n');
        $cropKey = $this->normalizeCropName($crop);
        $months = config("seasonal_crops.crops.{$cropKey}.plantingMonths.{$zone}", []);
        if ($months === []) {
            return false;
        }

        $maxMonth = max($months);

        return $month > $maxMonth;
    }

    /**
     * Best planting date suggestion: if window active → today (or 1st of current month if mid-month prefer next week).
     * If upcoming months remain → 1st day of next planting month this year.
     * If season passed → null.
     */
    public function bestPlantDate(string $crop, string $zone, CarbonInterface|string|null $date = null): ?Carbon
    {
        $date = $date instanceof CarbonInterface ? $date->copy() : Carbon::parse($date ?? now());
        $cropKey = $this->normalizeCropName($crop);
        $months = config("seasonal_crops.crops.{$cropKey}.plantingMonths.{$zone}", []);
        if ($months === []) {
            return null;
        }

        sort($months);
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');

        if ($this->plantingWindowActive($cropKey, $zone, $date)) {
            return $date->copy()->startOfDay();
        }

        foreach ($months as $m) {
            if ($m > $month) {
                return Carbon::create($year, $m, 1)->startOfDay();
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function knownCrops(): array
    {
        return array_keys(config('seasonal_crops.crops', []));
    }
}
