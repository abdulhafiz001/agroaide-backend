<?php

namespace App\Console\Commands;

use App\Models\CropWatch;
use App\Services\NotificationDispatcher;
use App\Services\SeasonalCalendarService;
use Illuminate\Console\Command;

class SendPlantingWindowAlerts extends Command
{
    protected $signature = 'agroaide:send-planting-window-alerts';

    protected $description = 'Notify users when watched crops enter their planting window';

    public function __construct(
        private SeasonalCalendarService $seasonalCalendar,
        private NotificationDispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $today = now()->toDateString();
        $sent = 0;

        $watches = CropWatch::where('notify_when_planting_window', true)
            ->with('user')
            ->get();

        $this->info("Checking {$watches->count()} crop watch(es) for planting windows ({$today}).");

        foreach ($watches as $watch) {
            $user = $watch->user;
            if (! $user) {
                continue;
            }

            if ($watch->last_notified_on && $watch->last_notified_on->toDateString() === $today) {
                continue;
            }

            $lat = $user->farm_latitude !== null ? (float) $user->farm_latitude : null;
            $lng = $user->farm_longitude !== null ? (float) $user->farm_longitude : null;
            $zone = $this->seasonalCalendar->resolveZone($lat, $lng, $user->farm_location);

            if (! $this->seasonalCalendar->plantingWindowActive($watch->crop, $zone, now())) {
                continue;
            }

            $zoneLabel = config("seasonal_crops.zones.{$zone}.label", $zone);
            $title = "Planting window: {$watch->crop}";
            $message = "It's a good time to plant {$watch->crop} in your {$zoneLabel} zone.";

            $notification = $this->dispatcher->notify(
                $user,
                'planting_window',
                $title,
                $message,
                [
                    'crop' => $watch->crop,
                    'zone' => $zone,
                    'watchId' => $watch->id,
                ],
                [
                    'push' => true,
                    'dedupeMinutes' => 1440,
                    'dedupeKey' => 'watchId',
                ],
            );

            if ($notification) {
                $watch->update(['last_notified_on' => $today]);
                $sent++;
            }
        }

        $this->info("Sent {$sent} planting window alert(s).");

        return self::SUCCESS;
    }
}
