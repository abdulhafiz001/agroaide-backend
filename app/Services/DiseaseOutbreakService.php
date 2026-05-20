<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\FarmImageAnalysis;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DiseaseOutbreakService
{
    private const OUTBREAK_THRESHOLD = 7;
    private const RADIUS_KM = 10;
    private const LOOKBACK_DAYS = 14;

    /**
     * Check if a newly logged disease scan triggers an outbreak alert.
     */
    public function checkForOutbreak(FarmImageAnalysis $scan): void
    {
        if (! $scan->disease_name || ! $scan->latitude || ! $scan->longitude) {
            return;
        }

        $nearbyCount = $this->countNearbyDiseaseReports(
            $scan->disease_name,
            $scan->latitude,
            $scan->longitude,
            self::RADIUS_KM,
            self::LOOKBACK_DAYS,
        );

        if ($nearbyCount >= self::OUTBREAK_THRESHOLD) {
            $this->triggerOutbreakAlert($scan->disease_name, $scan->latitude, $scan->longitude, $nearbyCount);
        }
    }

    /**
     * Run a full outbreak scan across all recent disease reports.
     * Called by the scheduled command.
     */
    public function runOutbreakDetection(): int
    {
        $alertsTriggered = 0;
        $cutoff = now()->subDays(self::LOOKBACK_DAYS);

        $recentDiseases = FarmImageAnalysis::whereNotNull('disease_name')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('created_at', '>=', $cutoff)
            ->select('disease_name', DB::raw('AVG(latitude) as center_lat'), DB::raw('AVG(longitude) as center_lng'), DB::raw('COUNT(DISTINCT user_id) as farmer_count'))
            ->groupBy('disease_name')
            ->having('farmer_count', '>=', self::OUTBREAK_THRESHOLD)
            ->get();

        foreach ($recentDiseases as $cluster) {
            $this->triggerOutbreakAlert(
                $cluster->disease_name,
                $cluster->center_lat,
                $cluster->center_lng,
                $cluster->farmer_count,
            );
            $alertsTriggered++;
        }

        return $alertsTriggered;
    }

    /**
     * Get heatmap data for the outbreak map.
     */
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

    /**
     * Get outbreak alerts relevant to a specific user.
     */
    public function getAlertsForUser(User $user): array
    {
        if (! $user->farm_latitude || ! $user->farm_longitude) {
            return [];
        }

        return $user->appNotifications()
            ->where('type', 'disease_outbreak')
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

    private function countNearbyDiseaseReports(string $disease, float $lat, float $lng, float $radiusKm, int $days): int
    {
        $cutoff = now()->subDays($days);

        return FarmImageAnalysis::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('disease_name', $disease)
            ->where('created_at', '>=', $cutoff)
            ->whereRaw(
                '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= ?',
                [$lat, $lng, $lat, $radiusKm]
            )
            ->distinct('user_id')
            ->count('user_id');
    }

    private function triggerOutbreakAlert(string $disease, float $lat, float $lng, int $reportCount): void
    {
        $nearbyUsers = User::whereNotNull('farm_latitude')
            ->whereNotNull('farm_longitude')
            ->whereRaw(
                '(6371 * acos(cos(radians(?)) * cos(radians(farm_latitude)) * cos(radians(farm_longitude) - radians(?)) + sin(radians(?)) * sin(radians(farm_latitude)))) <= ?',
                [$lat, $lng, $lat, self::RADIUS_KM * 2]
            )
            ->get();

        $existingAlertUserIds = AppNotification::where('type', 'disease_outbreak')
            ->where('created_at', '>=', now()->subDays(3))
            ->whereJsonContains('data->disease', $disease)
            ->pluck('user_id')
            ->toArray();

        foreach ($nearbyUsers as $user) {
            if (in_array($user->id, $existingAlertUserIds)) {
                continue;
            }

            AppNotification::create([
                'user_id' => $user->id,
                'type' => 'disease_outbreak',
                'title' => "Disease Alert: {$disease}",
                'message' => "{$reportCount} farmers within {$this->formatDistance($lat, $lng, $user->farm_latitude, $user->farm_longitude)} of your farm have reported {$disease}. Take preventive action now.",
                'data' => [
                    'disease' => $disease,
                    'reportCount' => $reportCount,
                    'centerLat' => $lat,
                    'centerLng' => $lng,
                ],
            ]);

            $this->sendPushNotification($user, $disease, $reportCount);
        }
    }

    private function formatDistance(float $lat1, float $lng1, float $lat2, float $lng2): string
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c;

        return round($distance, 1) . 'km';
    }

    private function sendPushNotification(User $user, string $disease, int $reportCount): void
    {
        if (empty($user->push_token)) {
            return;
        }

        try {
            \Illuminate\Support\Facades\Http::post('https://exp.host/--/api/v2/push/send', [
                'to' => $user->push_token,
                'title' => "⚠️ Disease Alert: {$disease}",
                'body' => "{$reportCount} nearby farmers reported {$disease}. Open AgroAide for prevention tips.",
                'data' => ['type' => 'disease_outbreak', 'disease' => $disease],
                'sound' => 'default',
                'priority' => 'high',
            ]);
        } catch (\Exception $e) {
            Log::warning('Push notification failed', ['user' => $user->id, 'error' => $e->getMessage()]);
        }
    }
}
