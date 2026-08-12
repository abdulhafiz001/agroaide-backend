<?php

namespace Tests\Feature;

use App\Jobs\ProcessCropScan;
use App\Models\FarmImageAnalysis;
use App\Models\User;
use App\Services\CloudinaryStorageService;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class DiagnosisHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_registration_cannot_create_staff(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'fullName' => 'Farmer',
            'email' => 'farmer@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
            'termsVersion' => config('legal.terms.version'),
            'privacyVersion' => config('legal.privacy.version'),
        ]);

        $response->assertCreated();
        $this->assertSame('farmer', User::where('email', 'farmer@example.test')->value('role'));
    }

    public function test_scan_is_stored_before_job_dispatch_and_returns_accepted(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $cloudinary = Mockery::mock(CloudinaryStorageService::class);
        $cloudinary->shouldReceive('uploadBuffer')->once()->andReturn([
            'public_id' => 'agroaide/uploads/farm-scans/1/test-scan',
            'secure_url' => 'https://res.cloudinary.com/demo/image/upload/v1/agroaide/uploads/farm-scans/1/test-scan.png',
            'url' => 'https://res.cloudinary.com/demo/image/upload/v1/agroaide/uploads/farm-scans/1/test-scan.png',
            'bytes' => 68,
            'format' => 'png',
        ]);
        $this->app->instance(CloudinaryStorageService::class, $cloudinary);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/farm/analyze-image', [
            'imageBase64' => 'data:image/png;base64,'.base64_encode(
                hex2bin('89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000d4944415408d763f8cfc0f01f00050001ff89993d1d0000000049454e44ae426082')
            ),
        ]);

        $response->assertAccepted()
            ->assertJsonPath('scan.processingState', 'queued')
            ->assertJsonPath('scan.imageUrl', 'https://res.cloudinary.com/demo/image/upload/v1/agroaide/uploads/farm-scans/1/test-scan.png');
        $scan = FarmImageAnalysis::findOrFail($response->json('scan.id'));
        $this->assertNotNull($scan->image_path);
        $this->assertSame('agroaide/uploads/farm-scans/1/test-scan', $scan->image_public_id);
        $this->assertNotNull($scan->image_url);
        Queue::assertPushed(ProcessCropScan::class, fn ($job) => $job->scanId === $scan->id);
    }

    public function test_farmer_feedback_disputes_scan_and_removes_outbreak_eligibility(): void
    {
        $user = User::factory()->create();
        $scan = FarmImageAnalysis::create([
            'user_id' => $user->id,
            'condition' => 'diseased',
            'disease_name' => 'Late Blight',
            'processing_state' => 'completed',
            'verification_state' => 'auto_verified',
            'outbreak_eligible' => true,
            'result_json' => ['condition' => 'diseased'],
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/farm/scans/{$scan->id}/feedback", ['verdict' => 'incorrect'])
            ->assertCreated()
            ->assertJsonPath('scan.verificationState', 'disputed');

        $scan->refresh();
        $this->assertFalse($scan->outbreak_eligible);
        $this->assertSame('disputed', $scan->verification_state);
    }

    public function test_repeated_feedback_updates_one_current_record_and_routes_are_throttled(): void
    {
        $user = User::factory()->create();
        $scan = FarmImageAnalysis::create([
            'user_id' => $user->id, 'condition' => 'fair', 'result_json' => [],
            'verification_state' => 'pending_review',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/farm/scans/{$scan->id}/feedback", ['verdict' => 'unsure'])
            ->assertCreated();
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/farm/scans/{$scan->id}/feedback", ['verdict' => 'correct'])
            ->assertOk();

        $this->assertDatabaseCount('scan_feedback', 1);
        $this->assertDatabaseHas('scan_feedback', [
            'farm_image_analysis_id' => $scan->id, 'user_id' => $user->id, 'verdict' => 'correct',
        ]);
        $feedbackRoute = app('router')->getRoutes()->match(
            Request::create("/api/farm/scans/{$scan->id}/feedback", 'POST')
        );
        $this->assertContains('throttle:feedback', $feedbackRoute->middleware());
        $loginRoute = app('router')->getRoutes()->getByName('staff.authenticate');
        $this->assertContains('throttle:staff-login', $loginRoute->middleware());
    }

    public function test_staff_dashboard_requires_staff_role_and_csrf_protected_web_session(): void
    {
        $this->withoutVite();
        $farmer = User::factory()->create(['role' => 'farmer']);
        $agronomist = User::factory()->create(['role' => 'agronomist']);

        $this->actingAs($farmer)->get('/staff')->assertForbidden();
        $this->actingAs($agronomist)->get('/staff')->assertOk();
        $route = app('router')->getRoutes()->getByName('staff.logout');
        $this->assertContains('web', $route->middleware());
        $groups = app(Kernel::class)->getMiddlewareGroups();
        $this->assertContains(ValidateCsrfToken::class, $groups['web']);
    }
}
