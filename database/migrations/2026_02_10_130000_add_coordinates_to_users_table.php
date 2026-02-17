<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('farm_latitude', 10, 7)->nullable()->after('farm_location');
            $table->decimal('farm_longitude', 10, 7)->nullable()->after('farm_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['farm_latitude', 'farm_longitude']);
        });
    }
};
