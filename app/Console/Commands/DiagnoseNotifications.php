<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\FcmPushService;
use Illuminate\Console\Command;

class DiagnoseNotifications extends Command
{
    protected $signature = 'agroaide:diagnose-notifications
                            {--send= : Optional user id to send a test FCM push}
                            {--email= : Optional user email to send a test FCM push}';

    protected $description = 'Check FCM config, scheduled notification jobs, and optionally send a test push';

    public function handle(FcmPushService $fcm): int
    {
        $this->info('AgroAide notification diagnostics');
        $this->newLine();

        $projectId = config('services.fcm.project_id');
        $credentialsPath = config('services.fcm.credentials');
        $readable = is_string($credentialsPath) && is_readable($credentialsPath);

        $this->line('FCM project id: '.($projectId ? 'set' : 'MISSING'));
        $this->line('FCM credentials path: '.($credentialsPath ?: 'MISSING'));
        $this->line('FCM credentials readable: '.($readable ? 'yes' : 'NO'));

        $withToken = User::query()->whereNotNull('push_token')->where('push_token', '!=', '')->count();
        $this->line("Users with push_token: {$withToken}");

        $this->newLine();
        $this->line('Expected scheduled notification jobs (see routes/console.php + supervisord schedule:work):');
        foreach ([
            'hourly — agroaide:detect-outbreaks',
            'every 30m — agroaide:send-task-reminders',
            'every 30m — agroaide:send-planting-reminders',
            'every 2h — agroaide:send-weather-alerts',
            'daily 06:30 — agroaide:send-daily-ai-insights',
            'daily — agroaide:send-planting-window-alerts',
            'daily — agroaide:send-boundary-reminders',
            'every 6h — agroaide:analyze-crop-watches',
        ] as $row) {
            $this->line(' - '.$row);
        }

        $user = null;
        if ($this->option('send')) {
            $user = User::find((int) $this->option('send'));
        } elseif ($this->option('email')) {
            $user = User::where('email', $this->option('email'))->first();
        }

        if ($user) {
            $this->newLine();
            if (empty($user->push_token)) {
                $this->error("User #{$user->id} has no push_token. Open the Android app (not Expo Go) while logged in so the device token can register.");

                return self::FAILURE;
            }

            $ok = $fcm->sendToUser(
                $user,
                'AgroAide test notification',
                'If you see this, FCM delivery is working.',
                ['type' => 'test', 'notificationId' => 'diag-'.now()->timestamp],
            );

            if ($ok) {
                $this->info("Test push accepted by FCM for user #{$user->id} ({$user->email}).");
            } else {
                $this->error('Test push failed. Check laravel.log for FCM send failed / OAuth errors.');

                return self::FAILURE;
            }
        } else {
            $this->newLine();
            $this->comment('Tip: re-run with --email=farmer@example.com to send a live test push.');
        }

        if (! $projectId || ! $readable) {
            $this->newLine();
            $this->error('FCM is not fully configured. Set FCM_PROJECT_ID and mount a readable FCM_CREDENTIALS_PATH.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Diagnostics complete.');

        return self::SUCCESS;
    }
}
