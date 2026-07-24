<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_tasks', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable()->after('user_id');
            $table->unique(['user_id', 'client_uuid']);
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable()->after('user_id');
            $table->unique(['user_id', 'client_uuid']);
        });
    }

    public function down(): void
    {
        Schema::table('calendar_tasks', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'client_uuid']);
            $table->dropColumn('client_uuid');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'client_uuid']);
            $table->dropColumn('client_uuid');
        });
    }
};
