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
    public function __construct(
        private WeatherService $weatherService,
        private AiAdvisorService $advisorService,
    ) {}

    public function snapshot(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $lat = (float) ($user->farm_latitude ?? 6.8402);
        $lng = (float) ($user->farm_longitude ?? 7.3705);

        $weather = $this->weatherService->getDashboardWeather($lat, $lng);

        $primaryAlert = ! empty($weather['alerts'])
            ? $weather['alerts'][0]
            : ['severity' => 'Low', 'title' => 'No alerts', 'advice' => 'All clear today.', 'gradient' => ['#2eb873', '#57b346']];

        $nextTask = CalendarTask::where('user_id', $user->id)
            ->where('completed', false)
            ->where('scheduled_date', '>=', now()->toDateString())
            ->orderBy('scheduled_date')
            ->orderByRaw("FIELD(period, 'morning', 'afternoon', 'evening')")
            ->first();

        $priorityTask = $nextTask ? [
            'title' => $nextTask->title,
            'progress' => 0,
            'estimatedImpact' => $nextTask->description ?? 'Complete this task to stay on schedule.',
            'actionItems' => [$nextTask->title],
        ] : [
            'title' => 'No pending tasks',
            'progress' => 100,
            'estimatedImpact' => 'You are all caught up. Consider adding new tasks.',
            'actionItems' => [],
        ];

        try {
            $aiInsights = $this->advisorService->dailyInsight($user);
        } catch (\Exception $e) {
            $aiInsights = [
                ['id' => 'tip-1', 'title' => 'Check your crops', 'description' => 'Do a quick morning inspection of your fields.'],
                ['id' => 'tip-2', 'title' => 'Review weather', 'description' => 'Plan your activities around today\'s forecast.'],
            ];
        }

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
            'aiInsights' => $aiInsights,
            'unreadNotifications' => $unreadNotifs,
            'currentWeather' => $weather['current'] ?? [],
        ]);
    }
}
