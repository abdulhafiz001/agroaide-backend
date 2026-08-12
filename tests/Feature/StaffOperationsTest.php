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
        $users = User::factory()->count(3)->create();
        FarmImageAnalysis::create(['user_id' => $users[0]->id, 'condition' => 'healthy', 'result_json' => []]);
        DB::table('journal_entries')->insert([
            'user_id' => $users[1]->id, 'type' => 'observation', 'note' => 'Checked crop',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('calendar_tasks')->insert([
            'user_id' => $users[2]->id, 'title' => 'Scout', 'scheduled_date' => today(),
            'completed' => true, 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($agronomist)->get('/staff')->assertOk()
            ->assertSee('Active farms (30 d)')
            ->assertSee('>3</p>', false);

        DB::table('journal_entries')->where('user_id', $users[1]->id)->delete();
        DB::table('calendar_tasks')->where('user_id', $users[2]->id)->delete();
        $this->actingAs($agronomist)->get('/staff')->assertOk()
            ->assertSee('Active farms (30 d)')
            ->assertSee('&lt;3', false);
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
