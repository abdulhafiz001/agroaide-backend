<?php

namespace App\Console\Commands;

use App\Services\MarketPriceService;
use Illuminate\Console\Command;

class SyncMarketPrices extends Command
{
    protected $signature = 'agroaide:sync-market-prices';

    protected $description = 'Pull Market Eye prices for nearest markets (daily); store snapshots + history on change';

    public function __construct(private MarketPriceService $marketPrices)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Syncing Market Eye prices…');
        $count = $this->marketPrices->syncAllUsers();
        $this->info("Synced {$count} market(s).");

        return self::SUCCESS;
    }
}
