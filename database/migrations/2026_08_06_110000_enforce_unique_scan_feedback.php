<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('scan_feedback')) {
            return;
        }

        DB::table('scan_feedback')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn ($row) => $row->farm_image_analysis_id.'|'.$row->user_id)
            ->each(function ($rows): void {
                DB::table('scan_feedback')->whereIn('id', $rows->skip(1)->pluck('id'))->delete();
            });

        if (! $this->hasIndex('scan_feedback', 'scan_feedback_scan_user_unique')) {
            Schema::table('scan_feedback', function (Blueprint $table): void {
                $table->unique(['farm_image_analysis_id', 'user_id'], 'scan_feedback_scan_user_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('scan_feedback')) {
            return;
        }

        if ($this->hasIndex('scan_feedback', 'scan_feedback_scan_user_unique')) {
            Schema::table('scan_feedback', function (Blueprint $table): void {
                $table->dropUnique('scan_feedback_scan_user_unique');
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $indexName);
    }
};
