<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_transactions', function (Blueprint $table) {
            $table->string('sale_item')->nullable()->after('note');
            $table->string('category_other')->nullable()->after('sale_item');
        });
    }

    public function down(): void
    {
        Schema::table('field_transactions', function (Blueprint $table) {
            $table->dropColumn(['sale_item', 'category_other']);
        });
    }
};
