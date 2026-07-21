<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\FarmField;
use App\Models\FarmImageAnalysis;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DiseaseOutbreakService
{
    /** Farmers with same disease + same crop inside radius → local warning */
    private const WARNING_THRESHOLD = 3;

    /** Farmers with same disease + same crop inside radius → outbreak alert */
    private const OUTBREAK_THRESHOLD = 10;

    /** Proximity radius in kilometers (Haversine / great-circle). */
    private const RADIUS_KM = 5;

    private const LOOKBACK_DAYS = 14;

    public function __construct(private NotificationDispatcher $dispatcher) {}

    /**
     * Check if a newly logged disease scan should warn neighbors or declare an outbreak.
     *
     * Distance uses the Haversine formula on a sphere (Earth radius ≈ 6371 km):
     *   a = sin²(Δlat/2) + cos(lat1)·cos(lat2)·sin²(Δlng/2)
     *   c = 2·atan2(√a, √(1−a))
     *   d = R·c
     * SQL equivalent (km):
     *   6371 * acos(cos(radians(lat1)) * cos(radians(lat2))
     *     * cos(radians(lng2) - radians(lng1))
     *     + sin(radians(lat1)) * sin(radians(lat2)))
     */
    public function checkForOutbreak(FarmImageAnalysis $scan): void
    {
        if (! $scan->disease_name || ! $scan->latitude || ! $scan->longitude) {
            return;
        }

        $scan->loadMissing(['farmField', 'user']);
        $crop = $this->resolveScanCrop($scan);
        if ($crop === '') {
            return;
        }

        $farmerCount = $this->countNearbySameCropFarmers(
            $scan->disease_name,
            $crop,
            (float) $scan->latitude,
            (float) $scan->longitude,
        );

        if ($farmerCount >= self::OUTBREAK_THRESHOLD) {
            $this->notifyNearbyGrowers(
                $scan->disease_name,
                $crop,
                (float) $scan->latitude,
                (float) $scan->longitude,
                $farmerCount,
                'outbreak',
            );

            return;
        }

        if ($farmerCount >= self::WARNING_THRESHOLD) {
            $this->notifyNearbyGrowers(
                $scan->disease_name,
                $crop,
                (float) $scan->latitude,
                (float) $scan->longitude,
                $farmerCount,
                'warning',
            );
        }
    }

    /**
     * Scheduled cluster scan across recent reports.
     */
    public function runOutbreakDetection(): int
    {
        $alertsTriggered = 0;
        $cutoff = now()->subDays(self::LOOKBACK_DAYS);

        $scans = FarmImageAnalysis::with(['farmField', 'user'])
            ->whereNotNull('disease_name')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('created_at', '>=', $cutoff)
            ->get();

        $clusters = [];
        foreach ($scans as $scan) {
            $crop = $this->resolveScanCrop($scan);
            if ($crop === '') {
                continue;
            }
            $key = strtolower(trim($scan->disease_name)).'|'.$crop;
            if (! isset($clusters[$key])) {
                $clusters[$key] = [
                    'disease' => $scan->disease_name,
                    'crop' => $crop,
                    'lat' => (float) $scan->latitude,
                    'lng' => (float) $scan->longitude,
                ];
            }
        }

        foreach ($clusters as $cluster) {
            $count = $this->countNearbySameCropFarmers(
                $cluster['disease'],
                $cluster['crop'],
                $cluster['lat'],
                $cluster['lng'],
            );

            if ($count >= self::OUTBREAK_THRESHOLD) {
                $this->notifyNearbyGrowers(
                    $cluster['disease'],
                    $cluster['crop'],
                    $cluster['lat'],
                    $cluster['lng'],
                    $count,
                    'outbreak',
                );
                $alertsTriggered++;
            } elseif ($count >= self::WARNING_THRESHOLD) {
                $this->notifyNearbyGrowers(
                    $cluster['disease'],
                    $cluster['crop'],
                    $cluster['lat'],
                    $cluster['lng'],
                    $count,
                    'warning',
                );
                $alertsTriggered++;
            }
        }

        return $alertsTriggered;
    }

    public function getHeatmapData(): array
    {
        $cutoff = now()->subDays(30);

        return FarmImageAnalysis::whereNotNull('disease_name')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('created_at', '>=', $cutoff)
            ->select('latitude', 'longitude', 'disease_name', 'created_at', DB::raw('COUNT(*) as report_count'))
            ->groupBy('latitude', 'longitude', 'disease_name', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit(500)
            ->get()
            ->map(fn ($row) => [
                'latitude' => (float) $row->latitude,
                'longitude' => (float) $row->longitude,
                'disease' => $row->disease_name,
                'count' => $row->report_count,
                'date' => $row->created_at->toIso8601String(),
            ])
            ->toArray();
    }

    public function getAlertsForUser(User $user): array
    {
        if (! $user->farm_latitude || ! $user->farm_longitude) {
            return [];
        }

        return $user->appNotifications()
            ->whereIn('type', ['disease_outbreak', 'disease_warning'])
            ->where('read', false)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn (AppNotification $n) => [
                'id' => (string) $n->id,
                'title' => $n->title,
                'message' => $n->message,
                'data' => $n->data,
                'createdAt' => $n->created_at->toIso8601String(),
            ])
            ->toArray();
    }

    private function countNearbySameCropFarmers(string $disease, string $crop, float $lat, float $lng): int
    {
        $cutoff = now()->subDays(self::LOOKBACK_DAYS);

        $reports = FarmImageAnalysis::with(['farmField', 'user'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('disease_name', $disease)
            ->where('created_at', '>=', $cutoff)
            ->whereRaw($this->haversineSql('latitude', 'longitude'), [$lat, $lng, $lat, self::RADIUS_KM])
            ->get();

        return $reports
            ->filter(fn (FarmImageAnalysis $report) => $this->reportMatchesCrop($report, $crop))
            ->pluck('user_id')
            ->unique()
            ->count();
    }

    private function notifyNearbyGrowers(
        string $disease,
        string $crop,
        float $lat,
        float $lng,
        int $reportCount,
        string $level,
    ): void {
        $type = $level === 'outbreak' ? 'disease_outbreak' : 'disease_warning';
        $prevention = $this->preventionAdvice($disease, $crop);

        $nearbyUsers = User::whereNotNull('farm_latitude')
            ->whereNotNull('farm_longitude')
            ->whereRaw($this->haversineSql('farm_latitude', 'farm_longitude'), [$lat, $lng, $lat, self::RADIUS_KM])
            ->get()
            ->filter(fn (User $user) => $this->userGrowsCrop($user, $crop));

        $recentNotified = AppNotification::where('type', $type)
            ->where('created_at', '>=', now()->subDays(3))
            ->whereJsonContains('data->disease', $disease)
            ->whereJsonContains('data->crop', $crop)
            ->pluck('user_id')
            ->all();

        foreach ($nearbyUsers as $user) {
            if (in_array($user->id, $recentNotified, true)) {
                continue;
            }

            // If outbreak already sent recently, skip lower-tier warning.
            if ($level === 'warning') {
                $hasOutbreak = AppNotification::where('user_id', $user->id)
                    ->where('type', 'disease_outbreak')
                    ->where('created_at', '>=', now()->subDays(3))
                    ->whereJsonContains('data->disease', $disease)
                    ->exists();
                if ($hasOutbreak) {
                    continue;
                }
            }

            $distance = $this->formatDistance($lat, $lng, (float) $user->farm_latitude, (float) $user->farm_longitude);

            if ($level === 'outbreak') {
                $title = "Outbreak alert: {$disease}";
                $message = "{$reportCount} farmers within {$distance} reported {$disease} on {$crop}. Act now. Prevention: {$prevention}";
            } else {
                $title = "Nearby disease warning: {$disease}";
                $message = "{$reportCount} farmers within 5km reported {$disease} on {$crop} (about {$distance} from you). Prevention: {$prevention}";
            }

            $this->dispatcher->notify(
                $user,
                $type,
                $title,
                $message,
                [
                    'disease' => $disease,
                    'crop' => $crop,
                    'reportCount' => $reportCount,
                    'level' => $level,
                    'radiusKm' => self::RADIUS_KM,
                    'centerLat' => $lat,
                    'centerLng' => $lng,
                    'prevention' => $prevention,
                ],
                ['push' => true, 'dedupeMinutes' => 60 * 24 * 3, 'dedupeKey' => 'disease'],
            );
        }
    }

    private function resolveScanCrop(FarmImageAnalysis $scan): string
    {
        $fromField = strtolower(trim((string) ($scan->farmField?->crop ?? '')));
        if ($fromField !== '') {
            return $fromField;
        }

        $userCrops = is_array($scan->user?->crops) ? $scan->user->crops : [];
        if (! empty($userCrops[0])) {
            return strtolower(trim((string) $userCrops[0]));
        }

        return '';
    }

    private function reportMatchesCrop(FarmImageAnalysis $report, string $crop): bool
    {
        $fieldCrop = strtolower(trim((string) ($report->farmField?->crop ?? '')));
        if ($fieldCrop !== '' && $this->cropsMatch($fieldCrop, $crop)) {
            return true;
        }

        $userCrops = is_array($report->user?->crops) ? $report->user->crops : [];

        return collect($userCrops)->contains(fn ($c) => $this->cropsMatch((string) $c, $crop));
    }

    private function userGrowsCrop(User $user, string $crop): bool
    {
        $profileCrops = is_array($user->crops) ? $user->crops : [];
        if (collect($profileCrops)->contains(fn ($c) => $this->cropsMatch((string) $c, $crop))) {
            return true;
        }

        return FarmField::where('user_id', $user->id)
            ->get()
            ->contains(fn (FarmField $field) => $this->cropsMatch((string) $field->crop, $crop));
    }

    private function cropsMatch(string $a, string $b): bool
    {
        $a = strtolower(trim($a));
        $b = strtolower(trim($b));

        return $a !== '' && $b !== '' && ($a === $b || str_contains($a, $b) || str_contains($b, $a));
    }

    private function preventionAdvice(string $disease, string $crop): string
    {
        $key = strtolower($disease);
        $tips = [
            'fall armyworm' => 'Scout leaves at dawn, remove egg masses, and apply recommended biological control early.',
            'army worm' => 'Scout leaves at dawn, remove egg masses, and apply recommended biological control early.',
            'blight' => 'Remove infected leaves, improve airflow, avoid overhead watering late in the day, and rotate crops.',
            'rust' => 'Remove heavily infected leaves, avoid working fields when wet, and consider resistant varieties next season.',
            'mosaic' => 'Control aphids/whiteflies, remove infected plants, and avoid sharing tools between healthy and sick plants.',
            'leaf spot' => 'Prune crowded foliage, clear fallen debris, and use clean seeds/seedlings next cycle.',
            'mildew' => 'Improve spacing for airflow, water at the base in the morning, and remove infected leaves promptly.',
        ];

        foreach ($tips as $needle => $tip) {
            if (str_contains($key, $needle)) {
                return $tip;
            }
        }

        return "Inspect your {$crop} daily, isolate affected plants, clear crop debris, and ask the AgroAide advisor for treatment steps for {$disease}.";
    }

    private function haversineSql(string $latColumn, string $lngColumn): string
    {
        return '(6371 * acos(LEAST(1, GREATEST(-1, cos(radians(?)) * cos(radians('.$latColumn.')) * cos(radians('.$lngColumn.') - radians(?)) + sin(radians(?)) * sin(radians('.$latColumn.')))))) <= ?';
    }

    private function formatDistance(float $lat1, float $lng1, float $lat2, float $lng2): string
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 1).'km';
    }
}
