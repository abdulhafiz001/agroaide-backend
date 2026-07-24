<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_fields', function (Blueprint $table) {
            $table->json('boundary_geojson')->nullable()->after('area_m2');
            $table->timestamp('boundary_updated_at')->nullable()->after('boundary_geojson');
            $table->uuid('client_uuid')->nullable()->after('user_id');
            $table->unique(['user_id', 'client_uuid']);
        });
    }

    public function down(): void
    {
        Schema::table('farm_fields', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'client_uuid']);
            $table->dropColumn(['boundary_geojson', 'boundary_updated_at', 'client_uuid']);
        });
    }
};
