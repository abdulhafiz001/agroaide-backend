<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_fields', function (Blueprint $table) {
            $table->timestamp('boundary_reminder_sent_at')->nullable()->after('boundary_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('farm_fields', function (Blueprint $table) {
            $table->dropColumn('boundary_reminder_sent_at');
        });
    }
};
