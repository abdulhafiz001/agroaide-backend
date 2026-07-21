<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarTask;
use App\Services\AiAdvisorService;
use App\Services\DiseaseOutbreakService;
use App\Services\TranslationService;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private WeatherService $weatherService,
        private AiAdvisorService $advisorService,
        private TranslationService $translationService,
        private DiseaseOutbreakService $outbreakService,
    ) {}

    /**
     * Fast snapshot: user, task, weather, alerts.
     * Weather/soil/AI require a real farm GPS — never invent another location.
     */
    public function snapshot(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $profileComplete = $this->isProfileComplete($user);
        $hasLocation = $user->farm_latitude !== null && $user->farm_longitude !== null;

        $weatherAlert = null;
        $soilHealth = [];
        $forecastFormatted = [];
        $currentWeather = [];
        $outbreakAlerts = [];

        if ($hasLocation) {
            $lat = (float) $user->farm_latitude;
            $lng = (float) $user->farm_longitude;

            try {
                $weather = $this->weatherService->getDashboardWeather($lat, $lng);
            } catch (\Exception $e) {
                $weather = [
                    'current' => [],
                    'soilHealth' => [],
                    'forecast' => [],
                    'alerts' => [],
                ];
            }

            $weatherAlert = ! empty($weather['alerts'])
                ? $weather['alerts'][0]
                : null;

            $soilHealth = $weather['soilHealth'] ?? [];
            $currentWeather = $weather['current'] ?? [];

            foreach (($weather['forecast'] ?? []) as $day) {
                $forecastFormatted[] = [
                    'day' => $day['day'] ?? 'N/A',
                    'high' => $day['high'] ?? 30,
                    'low' => $day['low'] ?? 22,
                    'precipitation' => $day['precipitation'] ?? 0,
                    'icon' => $day['icon'] ?? 'cloud',
                    'condition' => $day['condition'] ?? 'Unknown',
                ];
            }

            if ($weatherAlert && ($user->preferred_language ?? 'en') !== 'en') {
                $lang = $user->preferred_language;
                $weatherAlert['title'] = $this->translationService->translate($weatherAlert['title'], $lang);
                $weatherAlert['advice'] = $this->translationService->translate($weatherAlert['advice'], $lang);
            }

            $outbreakAlerts = $this->outbreakService->getAlertsForUser($user);
        }

        $today = now()->toDateString();
        $todayTasks = CalendarTask::where('user_id', $user->id)
            ->where('scheduled_date', $today)
            ->orderByRaw("FIELD(period, 'morning', 'afternoon', 'evening')")
            ->get();

        $completedToday = $todayTasks->where('completed', true)->count();
        $totalToday = $todayTasks->count();
        $progress = $totalToday > 0 ? (int) round(($completedToday / $totalToday) * 100) : 100;
        $nextTaskToday = $todayTasks->where('completed', false)->first();

        $priorityTask = $nextTaskToday ? [
            'title' => $nextTaskToday->title,
            'progress' => $progress,
            'estimatedImpact' => $nextTaskToday->description ?? 'Complete this task to stay on schedule.',
            'actionItems' => $todayTasks->where('completed', false)->pluck('title')->take(3)->toArray(),
        ] : [
            'title' => $totalToday > 0 ? 'All caught up for today!' : 'No tasks for today',
            'progress' => $progress,
            'estimatedImpact' => $totalToday > 0 ? 'Great job! All tasks for today are complete.' : 'Add tasks in Calendar to stay on track.',
            'actionItems' => [],
        ];

        $unreadNotifs = $user->appNotifications()
            ->where('read', false)
            ->count();

        return response()->json([
            'user' => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'farmName' => $user->farm_name ?? 'My Farm',
            ],
            'profileComplete' => $profileComplete,
            'hasFarmLocation' => $hasLocation,
            'weatherAlert' => $weatherAlert,
            'priorityTask' => $priorityTask,
            'soilHealth' => $soilHealth,
            'weatherForecast' => array_slice($forecastFormatted, 0, 5),
            'aiInsights' => [],
            'unreadNotifications' => $unreadNotifs,
            'currentWeather' => $currentWeather,
            'outbreakAlerts' => $outbreakAlerts,
        ]);
    }

    public function aiInsights(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! $this->isProfileComplete($user)) {
            return response()->json(['aiInsights' => []]);
        }

        try {
            $insights = $this->advisorService->dailyInsight($user);

            return response()->json(['aiInsights' => $insights]);
        } catch (\Exception $e) {
            return response()->json(['aiInsights' => []]);
        }
    }

    private function isProfileComplete($user): bool
    {
        if ($user->farm_latitude === null || $user->farm_longitude === null) {
            return false;
        }

        $crops = is_array($user->crops) ? $user->crops : [];
        $hasCrops = count($crops) > 0;
        $hasFarmName = filled($user->farm_name);
        $hasLocationLabel = filled($user->farm_location);

        return $hasCrops || $hasFarmName || $hasLocationLabel;
    }
}
