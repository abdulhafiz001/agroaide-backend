<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_field_id')->constrained('farm_fields')->cascadeOnDelete();
            $table->uuid('client_uuid')->nullable();
            $table->enum('type', ['EXPENSE', 'INCOME']);
            $table->enum('category', ['SEED', 'FERTILIZER', 'LABOR', 'HARVEST_SALE', 'OTHER']);
            $table->decimal('amount', 14, 2);
            $table->decimal('quantity', 14, 4)->nullable();
            $table->string('unit')->nullable();
            $table->date('occurred_on');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'client_uuid']);
            $table->index(['farm_field_id', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_transactions');
    }
};
