<?php

namespace App\Console\Commands;

use App\Models\FarmField;
use App\Services\NotificationDispatcher;
use Illuminate\Console\Command;

class SendBoundaryReminders extends Command
{
    protected $signature = 'agroaide:send-boundary-reminders';

    protected $description = 'Remind users to map field boundaries for fields without geojson after 24 hours';

    public function __construct(private NotificationDispatcher $dispatcher)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $cutoff = now()->subHours(24);
        $sent = 0;

        $fields = FarmField::query()
            ->whereNull('boundary_geojson')
            ->where('created_at', '<=', $cutoff)
            ->whereNull('boundary_reminder_sent_at')
            ->with('user')
            ->get();

        $this->info("Found {$fields->count()} field(s) missing boundaries (older than 24h).");

        foreach ($fields as $field) {
            $user = $field->user;
            if (! $user) {
                continue;
            }

            if (! $this->dispatcher->prefEnabled($user, 'fieldBoundaryReminders')) {
                continue;
            }

            $title = 'Map your field boundary';
            $message = "{$field->name} still has no mapped boundary. Draw it on the map so AgroAide can measure area accurately.";

            $notification = $this->dispatcher->notify(
                $user,
                'field_boundary_reminder',
                $title,
                $message,
                [
                    'fieldId' => (string) $field->id,
                    'fieldName' => $field->name,
                    'crop' => $field->crop,
                ],
                [
                    'push' => true,
                    'preference' => 'fieldBoundaryReminders',
                    'dedupeMinutes' => 1440,
                    'dedupeKey' => 'fieldId',
                ],
            );

            if ($notification) {
                $field->update(['boundary_reminder_sent_at' => now()]);
                $sent++;
            }
        }

        $this->info("Sent {$sent} boundary reminder(s).");

        return self::SUCCESS;
    }
}
