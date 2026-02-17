<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('crop');
            $table->decimal('area_hectares', 8, 2)->default(0);
            $table->unsignedTinyInteger('health_percentage')->default(80);
            $table->unsignedTinyInteger('moisture_percentage')->default(50);
            $table->date('planted_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_fields');
    }
};
