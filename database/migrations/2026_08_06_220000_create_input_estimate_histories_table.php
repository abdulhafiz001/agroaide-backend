<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('input_estimate_histories')) {
            return;
        }

        Schema::create('input_estimate_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_field_id')->constrained()->cascadeOnDelete();
            $table->string('crop', 120)->nullable();
            $table->decimal('area_m2', 12, 2)->nullable();
            $table->decimal('row_cm', 8, 2)->nullable();
            $table->decimal('intra_cm', 8, 2)->nullable();
            $table->unsignedInteger('population')->nullable();
            $table->json('result_json');
            $table->text('ai_summary')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'input_est_user_created_idx');
            $table->index(['farm_field_id', 'created_at'], 'input_est_field_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('input_estimate_histories');
    }
};
