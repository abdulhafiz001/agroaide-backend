<?php

use App\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone_normalized', 32)->nullable()->after('phone_number');
            $table->string('ai_response_depth', 16)->default('balanced');
            $table->string('ai_risk_tolerance', 16)->default('balanced');
            $table->boolean('ai_voice_tips')->default(true);
        });

        $seen = [];
        DB::table('users')->select('id', 'phone_number')->orderBy('id')->get()->each(function ($user) use (&$seen): void {
            $normalized = PhoneNumber::normalize($user->phone_number);
            if ($normalized === '' || isset($seen[$normalized])) {
                return;
            }
            $seen[$normalized] = true;
            DB::table('users')->where('id', $user->id)->update(['phone_normalized' => $normalized]);
        });
        Schema::table('users', fn (Blueprint $table) => $table->unique('phone_normalized'));

        Schema::create('user_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('terms_version');
            $table->string('privacy_version');
            $table->string('research_version')->nullable();
            $table->boolean('research_consent')->default(false);
            $table->timestamp('consented_at');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'consented_at']);
        });

        Schema::table('sync_action_logs', function (Blueprint $table): void {
            $table->dropUnique('sync_action_logs_uuid_unique');
            $table->unique(['user_id', 'uuid']);
        });
    }

    public function down(): void
    {
        Schema::table('sync_action_logs', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'uuid']);
            $table->unique('uuid');
        });
        Schema::dropIfExists('user_consents');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['phone_normalized']);
            $table->dropColumn(['phone_normalized', 'ai_response_depth', 'ai_risk_tolerance', 'ai_voice_tips']);
        });
    }
};
