<?php

namespace Tests\Feature;

use App\Jobs\RunEvaluation;
use App\Models\CanonicalLabel;
use App\Models\ConfidencePolicy;
use App\Models\EvaluationDataset;
use App\Models\EvaluationDatasetItem;
use App\Models\EvaluationRun;
use App\Models\FarmImageAnalysis;
use App\Models\ModelVersion;
use App\Models\PromptVersion;
use App\Models\User;
use Database\Seeders\DiagnosisDomainSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StaffOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DiagnosisDomainSeeder::class);
    }

    public function test_staff_pages_use_local_vite_assets_and_hide_admin_audit_from_agronomists(): void
    {
        DB::table('audit_logs')->insert([
            'action' => 'secret.admin.action', 'subject_type' => User::class,
            'subject_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $agronomist = User::factory()->create(['role' => 'agronomist']);

        $this->actingAs($agronomist)->get('/staff')
            ->assertOk()
            ->assertDontSee('cdn.tailwindcss.com')
            ->assertDontSee('secret.admin.action');
        $this->actingAs($agronomist)->get('/staff/audit')->assertForbidden();
    }

    public function test_only_admin_can_queue_runs_manage_policies_and_assign_roles(): void
    {
        Queue::fake();
        $agronomist = User::factory()->create(['role' => 'agronomist']);
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'farmer']);
        $dataset = $this->lockedDataset($admin);

        $this->actingAs($agronomist)->post("/staff/evaluations/datasets/{$dataset->id}/runs")->assertForbidden();
        $this->actingAs($agronomist)->post('/staff/confidence-policies', [])->assertForbidden();
        $this->actingAs($agronomist)->patch("/staff/users/{$target->id}/role", ['role' => 'admin'])->assertForbidden();

        $this->actingAs($admin)->post("/staff/evaluations/datasets/{$dataset->id}/runs")
            ->assertRedirect();
        Queue::assertPushed(RunEvaluation::class);

        $this->actingAs($admin)->post('/staff/confidence-policies', [
            'name' => 'review-policy', 'version' => '2',
            'retake_below' => 0.60, 'review_below' => 0.85,
            'require_canonical' => true,
        ])->assertRedirect();
        $policy = ConfidencePolicy::where('version', '2')->firstOrFail();
        $this->actingAs($admin)->post("/staff/confidence-policies/{$policy->id}/activate")->assertRedirect();
        $this->assertTrue($policy->fresh()->active);

        $this->actingAs($admin)->patch("/staff/users/{$target->id}/role", ['role' => 'agronomist'])
            ->assertRedirect();
        $this->assertSame('agronomist', $target->fresh()->role);
    }

    public function test_staff_can_view_dataset_provenance_run_metrics_and_comparison_without_fake_values(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $agronomist = User::factory()->create(['role' => 'agronomist']);
        $dataset = $this->lockedDataset($admin);
        $runA = $this->completedRun($dataset, $admin, 0.75);
        $runB = $this->completedRun($dataset, $admin, null);
        $label = CanonicalLabel::where('slug', 'tomato-late-blight')->firstOrFail();
        DB::table('evaluation_class_metrics')->insert([
            'evaluation_run_id' => $runA->id, 'canonical_label_id' => $label->id,
            'tp' => 3, 'fp' => 1, 'fn' => 1, 'tn' => 5,
            'precision' => 0.75, 'recall' => 0.75, 'f1' => 0.75, 'fpr' => 1 / 6,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($agronomist)->get("/staff/evaluations/datasets/{$dataset->id}")
            ->assertOk()->assertSee('Private source')->assertSee('Internal license')->assertSee(str_repeat('d', 64));
        $this->actingAs($agronomist)->get("/staff/evaluations/runs/{$runA->id}")
            ->assertOk()->assertSee('Tomato Late Blight')->assertSee('0.750');
        $this->actingAs($agronomist)->get("/staff/evaluations/compare?runs[]={$runA->id}&runs[]={$runB->id}")
            ->assertOk()->assertSee('75.0%')->assertSee('—');
    }

    public function test_dashboard_active_farms_uses_last_thirty_day_activity_and_suppresses_small_counts(): void
    {
        $agronomist = User::factory()->create(['role' => 'agronomist']);
        $farmers = User::factory()->count(3)->create(['role' => 'farmer']);
        FarmImageAnalysis::create(['user_id' => $farmers[0]->id, 'condition' => 'healthy', 'result_json' => []]);
        DB::table('journal_entries')->insert([
            'user_id' => $farmers[1]->id, 'type' => 'observation', 'note' => 'Checked crop',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('calendar_tasks')->insert([
            'user_id' => $farmers[2]->id, 'title' => 'Scout', 'scheduled_date' => today(),
            'completed' => true, 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($agronomist)->get('/staff')->assertOk()
            ->assertSee('Active farms (30 d)')
            ->assertSee('>3</p>', false);

        DB::table('journal_entries')->where('user_id', $farmers[1]->id)->delete();
        DB::table('calendar_tasks')->where('user_id', $farmers[2]->id)->delete();

        // Exact count is 1, displayed accurately without '<3' masking
        $this->actingAs($agronomist)->get('/staff')->assertOk()
            ->assertSee('Active farms (30 d)')
            ->assertSee('>1</p>', false)
            ->assertDontSee('&lt;3', false);
    }

    public function test_admin_can_view_users_directory_and_filter(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Jane']);
        $agronomist = User::factory()->create(['role' => 'agronomist', 'name' => 'Agronomist Bob']);
        $farmer = User::factory()->create(['role' => 'farmer', 'name' => 'Farmer John', 'farm_name' => 'Green Acres']);

        // Non-admin cannot access users directory
        $this->actingAs($agronomist)->get('/staff/users')->assertForbidden();

        // Admin can access users directory
        $this->actingAs($admin)->get('/staff/users')
            ->assertOk()
            ->assertSee('Admin Jane')
            ->assertSee('Agronomist Bob')
            ->assertSee('Farmer John')
            ->assertSee('Green Acres');

        // Role filter
        $this->actingAs($admin)->get('/staff/users?role=farmer')
            ->assertOk()
            ->assertSee('Farmer John')
            ->assertDontSee('Agronomist Bob');

        // Search filter
        $this->actingAs($admin)->get('/staff/users?search=Green+Acres')
            ->assertOk()
            ->assertSee('Farmer John')
            ->assertDontSee('Agronomist Bob');
    }

    public function test_admin_can_view_user_details_and_scans_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $agronomist = User::factory()->create(['role' => 'agronomist']);
        $farmer = User::factory()->create([
            'role' => 'farmer',
            'name' => 'Amaka Okafor',
            'farm_name' => 'Sun Valley Farm',
            'farm_location' => 'Enugu',
        ]);

        $scan = FarmImageAnalysis::create([
            'user_id' => $farmer->id,
            'condition' => 'diseased',
            'disease_name' => 'Cassava Mosaic',
            'normalized_confidence' => 0.92,
            'verification_state' => 'pending_review',
            'result_json' => [],
        ]);

        // Agronomist cannot view admin user details
        $this->actingAs($agronomist)->get("/staff/users/{$farmer->id}")->assertForbidden();

        // Admin can view user details
        $this->actingAs($admin)->get("/staff/users/{$farmer->id}")
            ->assertOk()
            ->assertSee('Amaka Okafor')
            ->assertSee('Sun Valley Farm')
            ->assertSee('Enugu')
            ->assertSee('Cassava Mosaic')
            ->assertSee('92.0%');
    }

    public function test_staff_can_view_and_update_profile_and_change_password(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Super Admin',
            'email' => 'admin@agroaide.test',
            'password' => bcrypt('OldPassword123!'),
        ]);

        // View profile
        $this->actingAs($admin)->get('/staff/profile')
            ->assertOk()
            ->assertSee('Super Admin')
            ->assertSee('admin@agroaide.test');

        // Update profile
        $this->actingAs($admin)->put('/staff/profile', [
            'name' => 'Updated Admin',
            'email' => 'newadmin@agroaide.test',
            'phone_number' => '+2348012345678',
        ])->assertRedirect();

        $this->assertSame('Updated Admin', $admin->fresh()->name);
        $this->assertSame('newadmin@agroaide.test', $admin->fresh()->email);

        // Update password with incorrect current password
        $this->actingAs($admin)->put('/staff/profile/password', [
            'current_password' => 'WrongPassword!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertSessionHasErrors('current_password');

        // Update password with correct current password
        $this->actingAs($admin)->put('/staff/profile/password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertRedirect();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewPassword123!', $admin->fresh()->password));
    }

    public function test_admin_can_access_dedicated_policies_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $agronomist = User::factory()->create(['role' => 'agronomist']);

        $this->actingAs($agronomist)->get('/staff/policies')->assertForbidden();

        $this->actingAs($admin)->get('/staff/policies')
            ->assertOk()
            ->assertSee('Confidence Policies')
            ->assertSee('Create New Policy Version');

        $this->actingAs($admin)->get('/staff/admin')
            ->assertRedirect(route('staff.policies.index'));
    }

    public function test_review_rejects_wrong_label_kinds_and_cross_crop_disease_pairs(): void
    {
        $agronomist = User::factory()->create(['role' => 'agronomist']);
        $owner = User::factory()->create();
        $scan = FarmImageAnalysis::create([
            'user_id' => $owner->id, 'condition' => 'diseased', 'result_json' => [],
            'verification_state' => 'pending_review',
        ]);
        $maize = CanonicalLabel::where('slug', 'maize')->firstOrFail();
        $tomatoDisease = CanonicalLabel::where('slug', 'tomato-late-blight')->firstOrFail();

        $this->actingAs($agronomist)->from('/staff')->post("/staff/scans/{$scan->id}/review", [
            'action' => 'correct', 'crop_label_id' => $tomatoDisease->id,
            'disease_label_id' => $tomatoDisease->id,
        ])->assertSessionHasErrors('crop_label_id');

        $this->actingAs($agronomist)->from('/staff')->post("/staff/scans/{$scan->id}/review", [
            'action' => 'correct', 'crop_label_id' => $maize->id,
            'disease_label_id' => $tomatoDisease->id,
        ])->assertSessionHasErrors('disease_label_id');
    }

    private function lockedDataset(User $admin): EvaluationDataset
    {
        $dataset = EvaluationDataset::create([
            'name' => 'Benchmark', 'version' => '1', 'source' => 'Private source',
            'license' => 'Internal license', 'checksum' => str_repeat('d', 64),
            'created_by' => $admin->id, 'locked_at' => now(),
        ]);
        EvaluationDatasetItem::withoutEvents(fn () => EvaluationDatasetItem::create([
            'evaluation_dataset_id' => $dataset->id, 'external_id' => 'sample-1',
            'image_path' => 'evaluation/sample.jpg', 'image_checksum' => str_repeat('e', 64),
            'crop_label_id' => CanonicalLabel::where('slug', 'tomato')->value('id'),
            'disease_label_id' => CanonicalLabel::where('slug', 'tomato-late-blight')->value('id'),
            'ground_truth_provenance' => 'Two agronomists agreed.',
        ]));

        return $dataset;
    }

    private function completedRun(EvaluationDataset $dataset, User $admin, ?float $accuracy): EvaluationRun
    {
        return EvaluationRun::create([
            'evaluation_dataset_id' => $dataset->id,
            'model_version_id' => ModelVersion::firstOrFail()->id,
            'prompt_version_id' => PromptVersion::firstOrFail()->id,
            'confidence_policy_id' => ConfidencePolicy::firstOrFail()->id,
            'created_by' => $admin->id, 'status' => 'completed', 'sample_count' => 4,
            'metrics' => ['accuracy' => $accuracy], 'completed_at' => now(),
        ]);
    }
}
