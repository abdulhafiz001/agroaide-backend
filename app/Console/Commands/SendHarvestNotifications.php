<?php

namespace App\Console\Commands;

use App\Services\HarvestEstimateService;
use Illuminate\Console\Command;

class SendHarvestNotifications extends Command
{
    protected $signature = 'agroaide:send-harvest-notifications';

    protected $description = 'Send delayed harvest estimates (~5h after planting date) and day-before harvest reminders';

    public function __construct(private HarvestEstimateService $harvest)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $estimates = $this->harvest->sendDueEstimateNotifications();
        $reminders = $this->harvest->sendDueHarvestReminders();
        $this->info("Harvest estimates sent: {$estimates}; day-before reminders: {$reminders}");

        return self::SUCCESS;
    }
}
