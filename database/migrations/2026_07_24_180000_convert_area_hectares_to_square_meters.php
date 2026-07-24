<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('farm_size_m2', 14, 2)->default(0)->after('farm_location');
        });

        Schema::table('farm_fields', function (Blueprint $table) {
            $table->decimal('area_m2', 14, 2)->default(0)->after('crop');
        });

        if (Schema::hasColumn('users', 'farm_size_hectares')) {
            DB::table('users')->orderBy('id')->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'farm_size_m2' => round(((float) ($user->farm_size_hectares ?? 0)) * 10000, 2),
                        ]);
                }
            });
        }

        if (Schema::hasColumn('farm_fields', 'area_hectares')) {
            DB::table('farm_fields')->orderBy('id')->chunkById(200, function ($fields): void {
                foreach ($fields as $field) {
                    DB::table('farm_fields')
                        ->where('id', $field->id)
                        ->update([
                            'area_m2' => round(((float) ($field->area_hectares ?? 0)) * 10000, 2),
                        ]);
                }
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'farm_size_hectares')) {
                $table->dropColumn('farm_size_hectares');
            }
        });

        Schema::table('farm_fields', function (Blueprint $table) {
            if (Schema::hasColumn('farm_fields', 'area_hectares')) {
                $table->dropColumn('area_hectares');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('farm_size_hectares', 8, 2)->default(0)->after('farm_location');
        });

        Schema::table('farm_fields', function (Blueprint $table) {
            $table->decimal('area_hectares', 8, 2)->default(0)->after('crop');
        });

        DB::table('users')->orderBy('id')->chunkById(200, function ($users): void {
            foreach ($users as $user) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'farm_size_hectares' => round(((float) ($user->farm_size_m2 ?? 0)) / 10000, 2),
                    ]);
            }
        });

        DB::table('farm_fields')->orderBy('id')->chunkById(200, function ($fields): void {
            foreach ($fields as $field) {
                DB::table('farm_fields')
                    ->where('id', $field->id)
                    ->update([
                        'area_hectares' => round(((float) ($field->area_m2 ?? 0)) / 10000, 2),
                    ]);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('farm_size_m2');
        });

        Schema::table('farm_fields', function (Blueprint $table) {
            $table->dropColumn('area_m2');
        });
    }
};
