<?php

namespace Tests\Feature;

use App\Models\ConfidencePolicy;
use App\Models\EvaluationDataset;
use App\Models\EvaluationRun;
use App\Models\ModelVersion;
use App\Models\PromptVersion;
use App\Models\User;
use Database\Seeders\DiagnosisDomainSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class EvaluationDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_locked_dataset_cannot_be_changed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $dataset = EvaluationDataset::create([
            'name' => 'Field benchmark', 'version' => '1',
            'source' => 'Private field collection', 'license' => 'Internal evaluation only',
            'checksum' => str_repeat('a', 64), 'created_by' => $admin->id, 'locked_at' => now(),
        ]);

        $this->expectException(LogicException::class);
        $dataset->update(['source' => 'changed']);
    }

    public function test_completed_run_cannot_be_changed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $dataset = EvaluationDataset::create([
            'name' => 'Benchmark', 'version' => '1', 'source' => 'Source', 'license' => 'License',
            'checksum' => str_repeat('b', 64), 'created_by' => $admin->id, 'locked_at' => now(),
        ]);
        $this->seed(DiagnosisDomainSeeder::class);
        $run = EvaluationRun::create([
            'evaluation_dataset_id' => $dataset->id,
            'model_version_id' => ModelVersion::first()->id,
            'prompt_version_id' => PromptVersion::first()->id,
            'confidence_policy_id' => ConfidencePolicy::first()->id,
            'created_by' => $admin->id, 'status' => 'completed', 'sample_count' => 0,
            'metrics' => ['accuracy' => null], 'completed_at' => now(),
        ]);

        $this->expectException(LogicException::class);
        $run->update(['sample_count' => 1]);
    }
}
