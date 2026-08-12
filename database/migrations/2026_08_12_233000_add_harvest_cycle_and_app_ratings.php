<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_fields', function (Blueprint $table): void {
            if (! Schema::hasColumn('farm_fields', 'harvested_at')) {
                $table->date('harvested_at')->nullable()->after('harvest_reminder_sent_at');
            }
            if (! Schema::hasColumn('farm_fields', 'yield_note')) {
                $table->string('yield_note', 255)->nullable()->after('harvested_at');
            }
            if (! Schema::hasColumn('farm_fields', 'planned_next_crop')) {
                $table->string('planned_next_crop', 100)->nullable()->after('yield_note');
            }
            if (! Schema::hasColumn('farm_fields', 'planned_plant_at')) {
                $table->date('planned_plant_at')->nullable()->after('planned_next_crop');
            }
            if (! Schema::hasColumn('farm_fields', 'next_plant_remind_2d_sent_at')) {
                $table->timestamp('next_plant_remind_2d_sent_at')->nullable()->after('planned_plant_at');
            }
            if (! Schema::hasColumn('farm_fields', 'next_plant_remind_on_sent_at')) {
                $table->timestamp('next_plant_remind_on_sent_at')->nullable()->after('next_plant_remind_2d_sent_at');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'app_rating_prompt_status')) {
                $table->string('app_rating_prompt_status', 20)->default('pending')->after('last_planting_prompt_on');
            }
        });

        if (! Schema::hasTable('app_ratings')) {
            Schema::create('app_ratings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->unsignedTinyInteger('stars');
                $table->text('comment')->nullable();
                $table->string('source', 40)->default('post_harvest');
                $table->timestamps();
                $table->index(['user_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_ratings');

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'app_rating_prompt_status')) {
                $table->dropColumn('app_rating_prompt_status');
            }
        });

        Schema::table('farm_fields', function (Blueprint $table): void {
            foreach ([
                'harvested_at',
                'yield_note',
                'planned_next_crop',
                'planned_plant_at',
                'next_plant_remind_2d_sent_at',
                'next_plant_remind_on_sent_at',
            ] as $col) {
                if (Schema::hasColumn('farm_fields', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
