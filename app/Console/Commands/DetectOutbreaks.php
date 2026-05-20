<?php

namespace App\Console\Commands;

use App\Services\DiseaseOutbreakService;
use Illuminate\Console\Command;

class DetectOutbreaks extends Command
{
    protected $signature = 'agroaide:detect-outbreaks';
    protected $description = 'Scan recent disease reports and trigger outbreak alerts';

    public function handle(DiseaseOutbreakService $service): int
    {
        $this->info('Running outbreak detection...');
        $alerts = $service->runOutbreakDetection();
        $this->info("Detection complete. {$alerts} outbreak alert(s) triggered.");

        return self::SUCCESS;
    }
}
