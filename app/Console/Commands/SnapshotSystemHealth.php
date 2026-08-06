<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SnapshotSystemHealth extends Command
{
    protected $signature = 'agroaide:health-snapshot';

    protected $description = 'Persist privacy-safe queue, scheduler, and provider health';

    public function handle(): int
    {
        $now = now();
        DB::table('provider_health_snapshots')->insert([
            'provider' => 'github-models',
            'status' => filled(config('services.groq.api_key')) ? 'configured' : 'not_configured',
            'latency_ms' => null, 'safe_error_code' => null, 'checked_at' => $now,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('system_job_runs')->insert([
            'job_type' => 'scheduler.health_snapshot', 'status' => 'completed',
            'started_at' => $now, 'heartbeat_at' => $now, 'finished_at' => $now,
            'safe_metadata' => json_encode([
                'queued_jobs' => DB::table('jobs')->count(),
                'failed_jobs' => DB::table('failed_jobs')->count(),
            ]),
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('provider_health_snapshots')->where('checked_at', '<', now()->subDays(30))->delete();
        DB::table('system_job_runs')->where('created_at', '<', now()->subDays(90))->delete();

        return self::SUCCESS;
    }
}
