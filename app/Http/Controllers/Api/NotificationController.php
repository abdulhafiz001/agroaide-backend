<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private WeatherService $weatherService) {}

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
        $todayKey = 'notif_generated_' . $user->id . '_' . date('Y-m-d');
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
                    if ($alert['severity'] !== 'Low') {
                        AppNotification::create([
                            'user_id' => $user->id,
                            'type' => 'weather',
                            'title' => $alert['title'],
                            'message' => $alert['advice'],
                        ]);
                    }
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
            AppNotification::create([
                'user_id' => $user->id,
                'type' => 'system',
                'title' => 'Task reminder: ' . $task->title,
                'message' => "You have a {$task->period} task scheduled today: {$task->title}.",
            ]);
        }

        if ($upcomingTasks->isEmpty() && ! ($user->farm_latitude && $user->farm_longitude)) {
            AppNotification::create([
                'user_id' => $user->id,
                'type' => 'ai',
                'title' => 'Welcome to AgroAide',
                'message' => 'Set up your farm location to get personalized weather alerts and farming insights.',
            ]);
        }

        cache()->put($todayKey, true, 86400);
    }
}
