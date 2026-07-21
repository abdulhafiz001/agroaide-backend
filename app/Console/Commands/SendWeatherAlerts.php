<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NotificationDispatcher;
use App\Services\WeatherService;
use Illuminate\Console\Command;

class SendWeatherAlerts extends Command
{
    protected $signature = 'agroaide:send-weather-alerts';

    protected $description = 'Send FCM weather alerts for farms with GPS coordinates';

    public function __construct(
        private WeatherService $weatherService,
        private NotificationDispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $users = User::query()
            ->whereNotNull('farm_latitude')
            ->whereNotNull('farm_longitude')
            ->get();

        $sent = 0;

        foreach ($users as $user) {
            try {
                $weather = $this->weatherService->getWeather(
                    (float) $user->farm_latitude,
                    (float) $user->farm_longitude,
                );
            } catch (\Throwable) {
                continue;
            }

            foreach (($weather['alerts'] ?? []) as $alert) {
                if (($alert['severity'] ?? 'Low') === 'Low') {
                    continue;
                }

                $alertKey = $alert['alertKey'] ?? md5(($alert['title'] ?? '').'|'.($alert['advice'] ?? ''));

                $notification = $this->dispatcher->notify(
                    $user,
                    'weather',
                    $alert['title'] ?? 'Weather alert',
                    $alert['advice'] ?? 'Check today’s weather conditions for your farm.',
                    [
                        'alertKey' => $alertKey,
                        'severity' => $alert['severity'] ?? 'Moderate',
                    ],
                    [
                        'push' => true,
                        'preference' => 'severeWeather',
                        'dedupeMinutes' => 60 * 12,
                        'dedupeKey' => 'alertKey',
                    ],
                );

                if ($notification) {
                    $sent++;
                }
            }
        }

        $this->info("Sent {$sent} weather alert(s).");

        return self::SUCCESS;
    }
}
