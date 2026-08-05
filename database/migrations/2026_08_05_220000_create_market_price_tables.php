<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_price_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('market_id')->index();
            $table->string('market_name');
            $table->string('market_area')->nullable();
            $table->string('market_city')->nullable();
            $table->string('market_state')->nullable();
            $table->decimal('market_lat', 10, 7)->nullable();
            $table->decimal('market_lng', 10, 7)->nullable();
            $table->string('crop_key')->index();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_name')->nullable();
            $table->string('product_slug')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('price_avg', 14, 2)->nullable();
            $table->decimal('price_min', 14, 2)->nullable();
            $table->decimal('price_max', 14, 2)->nullable();
            $table->string('currency', 8)->default('NGN');
            $table->string('confidence_level')->nullable();
            $table->boolean('available')->default(true);
            $table->date('source_updated_on')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['market_id', 'crop_key']);
        });

        Schema::create('market_price_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('market_id')->index();
            $table->string('crop_key')->index();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('price_avg', 14, 2);
            $table->string('currency', 8)->default('NGN');
            $table->date('recorded_on');
            $table->timestamps();

            $table->unique(['market_id', 'crop_key', 'recorded_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_price_history');
        Schema::dropIfExists('market_price_snapshots');
    }
};
