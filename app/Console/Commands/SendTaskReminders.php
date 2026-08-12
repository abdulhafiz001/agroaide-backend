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
            $this->warn('Sent 0 task reminder(s). Check: incomplete tasks for today, user push_token, FCM credentials, and dedupe window (240m).');
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

        $title = $kind === 'tomorrow'
            ? "Task Reminder — Tomorrow: {$task->title}"
            : "Task Reminder: {$task->title}";

        $body = $kind === 'tomorrow'
            ? ($task->description
                ? "Reminder: {$task->description}"
                : "Reminder: you have a {$task->period} farm task scheduled for tomorrow.")
            : ($task->description
                ? "Reminder: {$task->description}"
                : "Reminder: don't forget your {$task->period} farm task today.");

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
            ['push' => true, 'dedupeMinutes' => 240, 'dedupeKey' => 'taskId'],
        );

        return (bool) $notification;
    }

    private function shouldAutoIncludeTomorrow(): bool
    {
        // Evening runs also preview tomorrow so farmers can prepare.
        return (int) now()->format('H') >= 17;
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
