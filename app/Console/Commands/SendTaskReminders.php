<?php

namespace App\Console\Commands;

use App\Models\CalendarTask;
use App\Services\NotificationDispatcher;
use Illuminate\Console\Command;

class SendTaskReminders extends Command
{
    protected $signature = 'agroaide:send-task-reminders
                            {--date= : Override scheduled date (Y-m-d)}
                            {--period= : Override period (morning|afternoon|evening)}
                            {--include-tomorrow : Also remind about tomorrow\'s tasks}';

    protected $description = 'Send FCM push notifications for upcoming calendar tasks';

    public function __construct(private NotificationDispatcher $dispatcher)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $today = $this->option('date') ?: now()->toDateString();
        $currentPeriod = $this->option('period') ?: $this->getCurrentPeriod();
        $includeTomorrow = (bool) $this->option('include-tomorrow') || $this->shouldAutoIncludeTomorrow();

        $this->info("Checking tasks for {$today} / period={$currentPeriod}".($includeTomorrow ? ' (+ tomorrow preview)' : ''));

        $sent = 0;

        // Remind for ALL incomplete tasks today (not only the current period).
        // Period filtering was too narrow — farmers missed reminders outside morning/afternoon windows.
        $todayTasks = CalendarTask::where('scheduled_date', $today)
            ->where('completed', false)
            ->with('user')
            ->get();

        $this->line("Found {$todayTasks->count()} incomplete task(s) for today (period now={$currentPeriod}).");

        foreach ($todayTasks as $task) {
            if ($this->sendReminder($task, 'today')) {
                $sent++;
            }
        }

        if ($includeTomorrow) {
            $tomorrow = now()->addDay()->toDateString();
            $tomorrowTasks = CalendarTask::where('scheduled_date', $tomorrow)
                ->where('completed', false)
                ->with('user')
                ->get();

            $this->line("Found {$tomorrowTasks->count()} task(s) for tomorrow ({$tomorrow}).");

            foreach ($tomorrowTasks as $task) {
                if ($this->sendReminder($task, 'tomorrow')) {
                    $sent++;
                }
            }
        }

        if ($sent === 0) {
            $this->warn('Sent 0 task reminder(s). Check: incomplete tasks for today, user push_token, FCM credentials, and dedupe window (1440m).');
            $this->warn('Tip: php artisan agroaide:send-task-reminders --include-tomorrow');
            $this->warn('Or: php artisan agroaide:diagnose-notifications --email=you@example.com --send-test');
        } else {
            $this->info("Sent {$sent} task reminder(s).");
        }

        return self::SUCCESS;
    }

    private function sendReminder(CalendarTask $task, string $kind): bool
    {
        $user = $task->user;
        if (! $user) {
            return false;
        }

        $cleanTitle = $this->cleanTaskText($task->title);
        $cleanDesc = $this->cleanTaskText($task->description);

        $title = $kind === 'tomorrow'
            ? "Task reminder (Tomorrow): {$cleanTitle}"
            : "Task reminder: {$cleanTitle}";

        $body = $cleanDesc !== ''
            ? $cleanDesc
            : ($kind === 'tomorrow'
                ? "You have a {$task->period} farm task scheduled for tomorrow."
                : "You have a {$task->period} farm task scheduled for today.");

        $notification = $this->dispatcher->notify(
            $user,
            'task_reminder',
            $title,
            $body,
            [
                'taskId' => $task->id,
                'period' => $task->period,
                'kind' => $kind,
                'scheduledDate' => optional($task->scheduled_date)?->toDateString(),
            ],
            ['push' => true, 'dedupeMinutes' => 1440, 'dedupeKey' => 'taskId'],
        );

        return (bool) $notification;
    }

    private function cleanTaskText(?string $text): string
    {
        if (! $text) {
            return '';
        }

        $cleaned = preg_replace('/\[\s*harvest[-_]window(?::[^\]]*)?\]/i', '', $text) ?? $text;
        $cleaned = preg_replace('/harvest[-_]window:fieldId?=\d+/i', '', $cleaned) ?? $cleaned;
        $cleaned = str_replace(["\xE2\x80\x94", "\xE2\x80\x93", '—', '–'], '-', $cleaned);
        $cleaned = preg_replace('/\s+/', ' ', $cleaned) ?? $cleaned;

        return trim($cleaned);
    }

    private function shouldAutoIncludeTomorrow(): bool
    {
        return false;
    }

    private function getCurrentPeriod(): string
    {
        $hour = (int) now()->format('H');
        if ($hour < 12) {
            return 'morning';
        }
        if ($hour < 17) {
            return 'afternoon';
        }

        return 'evening';
    }
}
