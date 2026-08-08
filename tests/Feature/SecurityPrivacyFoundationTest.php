<?php

namespace Tests\Feature;

use App\Models\FarmField;
use App\Models\FarmImageAnalysis;
use App\Models\PasswordResetOtp;
use App\Models\User;
use App\Services\DiseaseOutbreakService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityPrivacyFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_journal_field_must_belong_to_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $field = FarmField::create(['user_id' => $other->id, 'name' => 'Other', 'crop' => 'Maize', 'area_m2' => 10]);
        Sanctum::actingAs($user);

        $this->postJson('/api/farm/journal', ['note' => 'Private', 'farmFieldId' => $field->id])
            ->assertUnprocessable();
    }

    public function test_sync_uuid_is_idempotent_per_user_not_global(): void
    {
        $uuid = '11111111-1111-4111-8111-111111111111';
        foreach ([User::factory()->create(), User::factory()->create()] as $user) {
            Sanctum::actingAs($user);
            $this->postJson('/api/sync/delta', ['actions' => [[
                'uuid' => $uuid,
                'clientTimestamp' => now()->toIso8601String(),
                'actionType' => 'field.create',
                'payload' => ['name' => 'North', 'crop' => 'Maize'],
            ]]])->assertOk()->assertJsonPath('results.0.status', 'applied');
        }
    }

    public function test_offline_boundary_deletion_is_supported_and_owner_scoped(): void
    {
        $user = User::factory()->create();
        $field = FarmField::create([
            'user_id' => $user->id,
            'name' => 'North',
            'crop' => 'Maize',
            'area_m2' => 100,
            'boundary_geojson' => [
                'type' => 'Polygon',
                'coordinates' => [[[7.0, 9.0], [7.001, 9.0], [7.001, 9.001], [7.0, 9.0]]],
            ],
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/sync/delta', ['actions' => [[
            'uuid' => '22222222-2222-4222-8222-222222222222',
            'clientTimestamp' => now()->addMinute()->toIso8601String(),
            'actionType' => 'boundary.delete',
            'payload' => ['fieldId' => $field->id],
        ]]])->assertOk()->assertJsonPath('results.0.status', 'applied');

        $this->assertDatabaseHas('farm_fields', [
            'id' => $field->id,
            'user_id' => $user->id,
            'boundary_geojson' => null,
        ]);
    }

    public function test_existing_user_must_accept_current_legal_versions(): void
    {
        $user = User::factory()->create();
        $user->consents()->delete();
        Sanctum::actingAs($user);

        $this->getJson('/api/farm/overview')->assertStatus(428)->assertJsonPath('consentRequired', true);
        $this->postJson('/api/auth/consent', [
            'termsVersion' => config('legal.terms.version'),
            'privacyVersion' => config('legal.privacy.version'),
            'researchConsent' => false,
        ])->assertOk();
        $this->getJson('/api/farm/overview')->assertOk();
    }

    public function test_legal_pages_and_metadata_are_public(): void
    {
        $this->get('/legal/terms')->assertOk()->assertSee('Academic final-year research prototype');
        $this->get('/legal/privacy')->assertOk()->assertSee('three distinct users');
        $this->getJson('/api/legal')->assertOk()->assertJsonPath('terms.version', config('legal.terms.version'));
    }

    public function test_heatmap_suppresses_small_cells_and_returns_only_coarse_coordinates(): void
    {
        foreach (range(1, 3) as $i) {
            $user = User::factory()->create();
            FarmImageAnalysis::create([
                'user_id' => $user->id,
                'latitude' => 9.051 + ($i * 0.001),
                'longitude' => 7.491 + ($i * 0.001),
                'condition' => 'diseased',
                'disease_name' => 'Blight',
                'verification_state' => 'auto_verified',
                'outbreak_eligible' => true,
            ]);
        }
        $privateUser = User::factory()->create();
        FarmImageAnalysis::create([
            'user_id' => $privateUser->id,
            'latitude' => 8.12345,
            'longitude' => 6.54321,
            'condition' => 'diseased',
            'disease_name' => 'Rust',
            'verification_state' => 'auto_verified',
            'outbreak_eligible' => true,
        ]);

        $data = app(DiseaseOutbreakService::class)->getHeatmapData();
        $this->assertCount(1, $data);
        $this->assertArrayHasKey('gridLatitude', $data[0]);
        $this->assertArrayNotHasKey('latitude', $data[0]);
        $this->assertSame(3, $data[0]['farmerCount']);
    }

    public function test_ai_preferences_are_persisted_serialized_and_applied_to_prompt(): void
    {
        // Force OpenAI-compatible Groq path so we can assert on `messages`.
        // Local .env may set GEMINI_API_KEY, which would otherwise take priority.
        config([
            'services.gemini.api_key' => '',
            'services.nvidia.api_key' => '',
            'services.groq.api_key' => 'test-key',
        ]);
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'Done']]]])]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/auth/profile', [
            'aiResponseDepth' => 'deep',
            'aiRiskTolerance' => 'cautious',
        ])->assertOk()
            ->assertJsonPath('profile.aiResponseDepth', 'deep');

        $this->postJson('/api/advisor/chat', ['message' => 'What should I do?'])->assertOk();
        Http::assertSent(function ($request) {
            $data = $request->data();
            $system = (string) ($data['messages'][0]['content'] ?? '');

            return str_contains($system, 'RESPONSE DEPTH (deep)')
                && str_contains($system, 'RISK STYLE (cautious)');
        });
    }

    public function test_export_and_immediate_account_deletion(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass']);
        Sanctum::actingAs($user);

        $this->get('/api/privacy/export')->assertOk()
            ->assertHeader('content-type', 'application/json');
        $this->deleteJson('/api/auth/account', ['password' => 'secret-pass'])->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_login_rate_limiter_is_attached(): void
    {
        foreach (range(1, 11) as $attempt) {
            $response = $this->postJson('/api/auth/login', ['identifier' => 'none@example.com', 'password' => 'wrong']);
        }

        $response->assertTooManyRequests();
    }

    public function test_retention_command_purges_expired_otps(): void
    {
        $user = User::factory()->create();
        $old = PasswordResetOtp::create([
            'user_id' => $user->id,
            'code_hash' => 'hash',
            'expires_at' => now()->subDays(2),
            'attempts' => 0,
        ]);
        $old->forceFill(['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)])->save();

        $this->artisan('agroaide:purge-expired-personal-data')->assertSuccessful();
        $this->assertDatabaseMissing('password_reset_otps', ['id' => $old->id]);
    }
}
