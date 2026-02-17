<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_number')->nullable()->after('email');
            $table->string('farm_name')->nullable()->after('phone_number');
            $table->string('farm_location')->nullable()->after('farm_name');
            $table->decimal('farm_size_hectares', 8, 2)->default(0)->after('farm_location');
            $table->json('crops')->nullable()->after('farm_size_hectares');
            $table->enum('experience_level', ['beginner', 'intermediate', 'advanced'])->default('beginner')->after('crops');
            $table->string('soil_type')->default('Loamy')->after('experience_level');
            $table->enum('irrigation_access', ['rain-fed', 'drip', 'sprinkler', 'flood'])->default('drip')->after('soil_type');
            $table->string('avatar_color')->default('#57b346')->after('irrigation_access');
            $table->enum('preferred_theme', ['light', 'dark', 'field'])->default('light')->after('avatar_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone_number',
                'farm_name',
                'farm_location',
                'farm_size_hectares',
                'crops',
                'experience_level',
                'soil_type',
                'irrigation_access',
                'avatar_color',
                'preferred_theme',
            ]);
        });
    }
};
