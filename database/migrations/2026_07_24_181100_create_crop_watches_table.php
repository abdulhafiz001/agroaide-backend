<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crop_watches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('crop');
            $table->boolean('notify_when_planting_window')->default(true);
            $table->date('last_notified_on')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'crop']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crop_watches');
    }
};
