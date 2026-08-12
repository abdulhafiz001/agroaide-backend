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

        $status = $fcm->configurationStatus();

        $this->line('FCM project id: '.($status['projectIdSet'] ? 'set' : 'MISSING'));
        $this->line('FCM credentials path: '.($status['path'] ?: 'MISSING'));
        $this->line('FCM credentials path exists: '.($status['pathExists'] ? 'yes' : 'NO'));
        $this->line('FCM credentials path readable: '.($status['pathReadable'] ? 'yes' : 'NO'));
        $this->line('FCM_CREDENTIALS_JSON set: '.($status['jsonEnvSet'] ? 'yes' : 'no'));
        $this->line('FCM_CREDENTIALS_BASE64 set: '.($status['base64EnvSet'] ? 'yes' : 'no'));
        $this->line('FCM credentials source: '.$status['source']);
        $this->line('FCM ready to send: '.($status['configured'] ? 'yes' : 'NO'));

        if ($status['hint']) {
            $this->newLine();
            $this->warn($status['hint']);
        }

        $withToken = User::query()->whereNotNull('push_token')->where('push_token', '!=', '')->count();
        $this->newLine();
        $this->line("Users with push_token: {$withToken}");

        $this->newLine();
        $this->line('Expected scheduled notification jobs (see routes/console.php + supervisord schedule:work):');
        foreach ([
            'hourly — agroaide:detect-outbreaks',
            'every 30m — agroaide:send-task-reminders',
            'every 15m — agroaide:send-harvest-notifications',
            'every 30m — agroaide:send-planting-reminders',
            'every 2h — agroaide:send-weather-alerts',
            'daily 06:30 — agroaide:send-daily-ai-insights',
            'daily — agroaide:send-planting-window-alerts',
            'daily — agroaide:send-boundary-reminders',
            'every 6h — agroaide:analyze-crop-watches',
        ] as $row) {
            $this->line(' - '.$row);
        }

        if (! $status['configured']) {
            $this->newLine();
            $this->error('FCM is not fully configured — pushes will stay as in-app inbox rows only.');
            $this->line('Fastest Coolify fix:');
            $this->line('  1) On your PC: base64 -w0 firebase-service-account.json   (or base64 -i on macOS)');
            $this->line('  2) Coolify env: FCM_PROJECT_ID=agro-aide-c1595');
            $this->line('  3) Coolify env: FCM_CREDENTIALS_BASE64=<paste the base64 string>');
            $this->line('  4) Redeploy / restart the container, then re-run this command with --email=...');

            return self::FAILURE;
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

        $this->newLine();
        $this->info('Diagnostics complete.');

        return self::SUCCESS;
    }
}
