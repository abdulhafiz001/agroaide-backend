<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarTask;
use App\Services\AiAdvisorService;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private const FALLBACK_AI_INSIGHTS = [
        ['id' => 'tip-1', 'title' => 'Check your crops', 'description' => 'Do a quick morning inspection of your fields.'],
        ['id' => 'tip-2', 'title' => 'Review weather', 'description' => 'Plan your activities around today\'s forecast.'],
    ];

    public function __construct(
        private WeatherService $weatherService,
        private AiAdvisorService $advisorService,
    ) {}

    /**
     * Fast snapshot: user, task, weather, alerts. AI insights use fallback.
     * Call /dashboard/ai-insights separately for personalized AI (non-blocking).
     */
    public function snapshot(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $lat = (float) ($user->farm_latitude ?? 6.8402);
        $lng = (float) ($user->farm_longitude ?? 7.3705);

        try {
            $weather = $this->weatherService->getDashboardWeather($lat, $lng);
        } catch (\Exception $e) {
            $weather = [
                'current' => ['temperature' => 28, 'humidity' => 65, 'condition' => 'Unknown', 'icon' => 'cloud'],
                'soilHealth' => [
                    ['label' => 'Moisture', 'value' => 55, 'unit' => '%', 'icon' => 'droplets', 'tone' => 'info'],
                    ['label' => 'Soil temp', 'value' => 26, 'unit' => '°C', 'icon' => 'thermometer', 'tone' => 'neutral'],
                    ['label' => 'Humidity', 'value' => 65, 'unit' => '%', 'icon' => 'cloud', 'tone' => 'info'],
                    ['label' => 'Wind', 'value' => 8, 'unit' => 'km/h', 'icon' => 'wind', 'tone' => 'neutral'],
                ],
                'forecast' => [
                    ['day' => 'Today', 'high' => 30, 'low' => 22, 'precipitation' => 0, 'icon' => 'cloud-sun', 'condition' => 'Unknown'],
                ],
                'alerts' => [['severity' => 'Low', 'title' => 'Weather unavailable', 'advice' => 'Check back shortly.', 'gradient' => ['#95a5a6', '#7f8c8d']]],
            ];
        }

        $primaryAlert = ! empty($weather['alerts'])
            ? $weather['alerts'][0]
            : ['severity' => 'Low', 'title' => 'No alerts', 'advice' => 'All clear today.', 'gradient' => ['#2eb873', '#57b346']];

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

        $forecastFormatted = [];
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

        $unreadNotifs = $user->appNotifications()
            ->where('read', false)
            ->count();

        return response()->json([
            'user' => [
                'name' => $user->name,
                'farmName' => $user->farm_name ?? 'My Farm',
            ],
            'weatherAlert' => $primaryAlert,
            'priorityTask' => $priorityTask,
            'soilHealth' => $weather['soilHealth'] ?? [],
            'weatherForecast' => array_slice($forecastFormatted, 0, 5),
            'aiInsights' => self::FALLBACK_AI_INSIGHTS,
            'unreadNotifications' => $unreadNotifs,
            'currentWeather' => $weather['current'] ?? [],
        ]);
    }

    /**
     * AI insights only (can be slow). Fetched after main dashboard loads.
     */
    public function aiInsights(Request $request): JsonResponse
    {
        $user = $request->user();
        try {
            $insights = $this->advisorService->dailyInsight($user);
            return response()->json(['aiInsights' => $insights]);
        } catch (\Exception $e) {
            return response()->json(['aiInsights' => self::FALLBACK_AI_INSIGHTS]);
        }
    }
}
