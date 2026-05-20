<?php

namespace App\Console\Commands;

use App\Models\CalendarTask;
use App\Models\AppNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendTaskReminders extends Command
{
    protected $signature = 'agroaide:send-task-reminders';
    protected $description = 'Send push notifications for upcoming calendar tasks';

    public function handle(): int
    {
        $today = now()->toDateString();
        $currentPeriod = $this->getCurrentPeriod();

        $tasks = CalendarTask::where('scheduled_date', $today)
            ->where('period', $currentPeriod)
            ->where('completed', false)
            ->with('user')
            ->get();

        $sent = 0;
        foreach ($tasks as $task) {
            $user = $task->user;
            if (! $user || empty($user->push_token)) {
                continue;
            }

            $existing = AppNotification::where('user_id', $user->id)
                ->where('type', 'task_reminder')
                ->where('created_at', '>=', now()->subHours(4))
                ->whereJsonContains('data->taskId', $task->id)
                ->exists();

            if ($existing) {
                continue;
            }

            AppNotification::create([
                'user_id' => $user->id,
                'type' => 'task_reminder',
                'title' => "Task Reminder: {$task->title}",
                'message' => $task->description ?? "Don't forget your {$currentPeriod} task.",
                'data' => ['taskId' => $task->id, 'period' => $currentPeriod],
            ]);

            try {
                Http::post('https://exp.host/--/api/v2/push/send', [
                    'to' => $user->push_token,
                    'title' => "📋 {$task->title}",
                    'body' => $task->description ?? "Time for your {$currentPeriod} farming task.",
                    'data' => ['type' => 'task_reminder', 'taskId' => $task->id],
                    'sound' => 'default',
                ]);
                $sent++;
            } catch (\Exception $e) {
                Log::warning('Task reminder push failed', ['user' => $user->id, 'error' => $e->getMessage()]);
            }
        }

        $this->info("Sent {$sent} task reminder(s).");
        return self::SUCCESS;
    }

    private function getCurrentPeriod(): string
    {
        $hour = (int) now()->format('H');
        if ($hour < 12) return 'morning';
        if ($hour < 17) return 'afternoon';
        return 'evening';
    }
}
