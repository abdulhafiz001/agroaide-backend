<?php

namespace App\Console\Commands;

use App\Models\PlantingReminder;
use App\Services\NotificationDispatcher;
use Illuminate\Console\Command;

class SendPlantingReminders extends Command
{
    protected $signature = 'agroaide:send-planting-reminders';

    protected $description = 'Send FCM for planting reminders (2 days before + planting day)';

    public function __construct(private NotificationDispatcher $dispatcher)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = now();
        $sent = 0;

        $due2d = PlantingReminder::with('user')
            ->whereNull('sent_2d_at')
            ->where('remind_2d_at', '<=', $now)
            ->get();

        foreach ($due2d as $reminder) {
            if (! $reminder->user) {
                continue;
            }
            $n = $this->dispatcher->notify(
                $reminder->user,
                'planting_reminder',
                "Plant {$reminder->crop} in 2 days",
                "Reminder: planting day for {$reminder->crop} is {$reminder->plant_on->toDateString()}. Prepare seed and land.",
                [
                    'crop' => $reminder->crop,
                    'plantOn' => $reminder->plant_on->toDateString(),
                    'kind' => 'two_days_before',
                    'reminderId' => $reminder->id,
                ],
                ['push' => true, 'preference' => 'plantingWindowAlerts'],
            );
            if ($n) {
                $reminder->update(['sent_2d_at' => $now]);
                $sent++;
            }
        }

        $dueOn = PlantingReminder::with('user')
            ->whereNull('sent_on_at')
            ->where('remind_on_at', '<=', $now)
            ->get();

        foreach ($dueOn as $reminder) {
            if (! $reminder->user) {
                continue;
            }
            $n = $this->dispatcher->notify(
                $reminder->user,
                'planting_reminder',
                "Plant {$reminder->crop} today",
                "Today is your planting day for {$reminder->crop}. Good luck in the field!",
                [
                    'crop' => $reminder->crop,
                    'plantOn' => $reminder->plant_on->toDateString(),
                    'kind' => 'planting_day',
                    'reminderId' => $reminder->id,
                ],
                ['push' => true, 'preference' => 'plantingWindowAlerts'],
            );
            if ($n) {
                $reminder->update(['sent_on_at' => $now]);
                $sent++;
            }
        }

        $this->info("Sent {$sent} planting reminder(s).");

        return self::SUCCESS;
    }
}
