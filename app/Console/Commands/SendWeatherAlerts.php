<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NotificationDispatcher;
use App\Services\WeatherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendWeatherAlerts extends Command
{
    protected $signature = 'agroaide:send-weather-alerts';

    protected $description = 'Send FCM weather alerts using each farmer’s exact farm GPS';

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
            $coords = $user->farmCoordinates();
            if ($coords === null) {
                continue;
            }

            try {
                $weather = $this->weatherService->getWeatherForUser($user);
            } catch (\Throwable $e) {
                Log::warning('Weather alert fetch failed', [
                    'user_id' => $user->id,
                    'latitude' => $coords['latitude'],
                    'longitude' => $coords['longitude'],
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if ($weather === null) {
                continue;
            }

            foreach (($weather['alerts'] ?? []) as $alert) {
                if (($alert['severity'] ?? 'Low') === 'Low') {
                    continue;
                }

                $alertKey = $alert['alertKey'] ?? md5(($alert['title'] ?? '').'|'.($alert['advice'] ?? ''));
                $advice = (string) ($alert['advice'] ?? 'Check today’s weather conditions for your farm.');
                if (! str_contains($advice, $coords['label'])) {
                    $advice = "At your farm near {$coords['label']}: {$advice}";
                }

                $notification = $this->dispatcher->notify(
                    $user,
                    'weather',
                    $alert['title'] ?? 'Weather alert',
                    $advice,
                    [
                        'alertKey' => $alertKey,
                        'severity' => $alert['severity'] ?? 'Moderate',
                        'farmLatitude' => $coords['latitude'],
                        'farmLongitude' => $coords['longitude'],
                        'farmLocation' => $coords['label'],
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
