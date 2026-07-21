<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Services\NotificationDispatcher;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private WeatherService $weatherService,
        private NotificationDispatcher $dispatcher,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $this->autoGenerateNotifications($user);

        $notifications = $user->appNotifications()
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn (AppNotification $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'message' => $n->message,
                'read' => $n->read,
                'createdAt' => $n->created_at->toIso8601String(),
                'data' => $n->data,
            ]);

        $unreadCount = $user->appNotifications()->where('read', false)->count();

        return response()->json([
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = AppNotification::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $notification->update(['read' => true]);

        return response()->json(['message' => 'Marked as read.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        AppNotification::where('user_id', $request->user()->id)
            ->where('read', false)
            ->update(['read' => true]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    private function autoGenerateNotifications($user): void
    {
        $todayKey = 'notif_generated_'.$user->id.'_'.date('Y-m-d');
        if (cache()->has($todayKey)) {
            return;
        }

        if ($user->farm_latitude && $user->farm_longitude) {
            try {
                $weather = $this->weatherService->getWeather(
                    (float) $user->farm_latitude,
                    (float) $user->farm_longitude,
                );

                foreach (($weather['alerts'] ?? []) as $alert) {
                    if (($alert['severity'] ?? 'Low') === 'Low') {
                        continue;
                    }

                    $alertKey = $alert['alertKey'] ?? md5(($alert['title'] ?? '').'|'.($alert['advice'] ?? ''));

                    // In-app backup only; push is handled by agroaide:send-weather-alerts
                    $this->dispatcher->notify(
                        $user,
                        'weather',
                        $alert['title'] ?? 'Weather alert',
                        $alert['advice'] ?? 'Check today’s weather conditions for your farm.',
                        [
                            'alertKey' => $alertKey,
                            'severity' => $alert['severity'] ?? 'Moderate',
                        ],
                        [
                            'push' => false,
                            'preference' => 'severeWeather',
                            'dedupeMinutes' => 60 * 12,
                            'dedupeKey' => 'alertKey',
                        ],
                    );
                }
            } catch (\Exception $e) {
                // silently skip
            }
        }

        $upcomingTasks = $user->calendarTasks()
            ->where('completed', false)
            ->where('scheduled_date', now()->toDateString())
            ->get();

        foreach ($upcomingTasks as $task) {
            $this->dispatcher->notify(
                $user,
                'system',
                'Task reminder: '.$task->title,
                "You have a {$task->period} task scheduled today: {$task->title}.",
                ['taskId' => $task->id, 'period' => $task->period],
                ['push' => false, 'dedupeMinutes' => 60 * 12, 'dedupeKey' => 'taskId'],
            );
        }

        if ($upcomingTasks->isEmpty() && ! ($user->farm_latitude && $user->farm_longitude)) {
            $this->dispatcher->notify(
                $user,
                'ai',
                'Welcome to AgroAide',
                'Set up your farm location to get personalized weather alerts and farming insights.',
                ['welcome' => true],
                ['push' => false, 'dedupeMinutes' => 60 * 24 * 7, 'dedupeKey' => 'welcome'],
            );
        }

        cache()->put($todayKey, true, 86400);
    }
}
