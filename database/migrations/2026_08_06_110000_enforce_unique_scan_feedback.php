<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('scan_feedback')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn ($row) => $row->farm_image_analysis_id.'|'.$row->user_id)
            ->each(function ($rows): void {
                DB::table('scan_feedback')->whereIn('id', $rows->skip(1)->pluck('id'))->delete();
            });

        Schema::table('scan_feedback', function (Blueprint $table): void {
            $table->unique(['farm_image_analysis_id', 'user_id'], 'scan_feedback_scan_user_unique');
        });
    }

    public function down(): void
    {
        Schema::table('scan_feedback', function (Blueprint $table): void {
            $table->dropUnique('scan_feedback_scan_user_unique');
        });
    }
};
