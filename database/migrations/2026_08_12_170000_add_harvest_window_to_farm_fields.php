<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_fields', function (Blueprint $table) {
            $table->date('harvest_start_date')->nullable()->after('planted_at');
            $table->date('harvest_end_date')->nullable()->after('harvest_start_date');
            $table->timestamp('planted_at_recorded_at')->nullable()->after('harvest_end_date');
            $table->timestamp('harvest_estimate_notified_at')->nullable()->after('planted_at_recorded_at');
            $table->timestamp('harvest_reminder_sent_at')->nullable()->after('harvest_estimate_notified_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->date('last_planting_prompt_on')->nullable()->after('notification_preferences');
        });
    }

    public function down(): void
    {
        Schema::table('farm_fields', function (Blueprint $table) {
            $table->dropColumn([
                'harvest_start_date',
                'harvest_end_date',
                'planted_at_recorded_at',
                'harvest_estimate_notified_at',
                'harvest_reminder_sent_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_planting_prompt_on');
        });
    }
};
