<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NotificationDispatcher;
use Illuminate\Console\Command;

class TestOutbreakNotification extends Command
{
    protected $signature = 'agroaide:test-outbreak-notification
                            {--email= : Farmer email to notify}
                            {--user= : Farmer user id to notify}
                            {--level=outbreak : outbreak or warning}
                            {--disease=Late Blight : Disease name}
                            {--crop=Tomato : Crop name}';

    protected $description = 'Send a test disease outbreak/warning push + in-app notification (opens Disease Map in the app)';

    public function handle(NotificationDispatcher $dispatcher): int
    {
        $email = $this->option('email');
        $userId = $this->option('user');
        $level = in_array($this->option('level'), ['outbreak', 'warning'], true)
            ? $this->option('level')
            : 'outbreak';
        $disease = (string) $this->option('disease');
        $crop = (string) $this->option('crop');

        $user = null;
        if ($email) {
            $user = User::where('email', $email)->first();
        } elseif ($userId) {
            $user = User::find($userId);
        } else {
            $user = User::whereNotNull('push_token')->latest('id')->first()
                ?? User::latest('id')->first();
        }

        if (! $user) {
            $this->error('No farmer found. Pass --email=you@example.com or --user=1');

            return self::FAILURE;
        }

        $type = $level === 'outbreak' ? 'disease_outbreak' : 'disease_warning';
        $reportCount = $level === 'outbreak' ? 12 : 4;
        $prevention = "Remove infected leaves, avoid overhead watering, and apply a recommended {$crop} fungicide early.";

        if ($level === 'outbreak') {
            $title = "Outbreak alert: {$disease}";
            $message = "{$reportCount} farmers within 5km reported {$disease} on {$crop}. Act now. Prevention: {$prevention}";
        } else {
            $title = "Nearby disease warning: {$disease}";
            $message = "{$reportCount} farmers within 5km reported {$disease} on {$crop}. Prevention: {$prevention}";
        }

        $notification = $dispatcher->notify(
            $user,
            $type,
            $title,
            $message,
            [
                'disease' => $disease,
                'crop' => $crop,
                'reportCount' => $reportCount,
                'level' => $level,
                'radiusKm' => 5,
                'prevention' => $prevention,
                'test' => true,
            ],
            [
                'push' => true,
                // Bypass normal 3-day dedupe for manual testing
                'dedupeMinutes' => null,
            ],
        );

        if (! $notification) {
            $this->error('Notification was not created (check prefs / FCM setup).');

            return self::FAILURE;
        }

        $this->info("Sent {$type} notification #{$notification->id} to {$user->email} (user #{$user->id}).");
        $this->line('App deep link target: /(app)/outbreak-map');
        $this->line('Tap the push notification (or open it from in-app Notifications) to land on the Disease Map with alert details.');

        if (empty($user->push_token)) {
            $this->warn('This user has no push_token saved — in-app notification still created; push may not appear on device.');
        }

        return self::SUCCESS;
    }
}
