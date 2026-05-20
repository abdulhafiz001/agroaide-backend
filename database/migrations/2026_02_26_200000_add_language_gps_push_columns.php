<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('preferred_language', 5)->default('en')->after('preferred_theme');
            $table->string('push_token', 255)->nullable()->after('preferred_language');
        });

        Schema::table('farm_image_analyses', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('farm_field_id');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('disease_name')->nullable()->after('condition');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['preferred_language', 'push_token']);
        });

        Schema::table('farm_image_analyses', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'disease_name']);
        });
    }
};
