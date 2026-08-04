<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crop_watches', function (Blueprint $table) {
            $table->string('status', 32)->default('active')->after('notify_when_planting_window');
            $table->date('best_plant_date')->nullable()->after('last_notified_on');
            $table->string('last_analysis_status', 64)->nullable()->after('best_plant_date');
            $table->timestamp('last_analyzed_at')->nullable()->after('last_analysis_status');
        });

        Schema::create('planting_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crop_watch_id')->nullable()->constrained('crop_watches')->nullOnDelete();
            $table->unsignedBigInteger('notification_id')->nullable();
            $table->string('crop');
            $table->date('plant_on');
            $table->dateTime('remind_2d_at');
            $table->dateTime('remind_on_at');
            $table->timestamp('sent_2d_at')->nullable();
            $table->timestamp('sent_on_at')->nullable();
            $table->boolean('local_scheduled')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'plant_on']);
            $table->index(['remind_2d_at', 'sent_2d_at']);
            $table->index(['remind_on_at', 'sent_on_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planting_reminders');
        Schema::table('crop_watches', function (Blueprint $table) {
            $table->dropColumn(['status', 'best_plant_date', 'last_analysis_status', 'last_analyzed_at']);
        });
    }
};
