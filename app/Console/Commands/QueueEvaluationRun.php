<?php

namespace App\Console\Commands;

use App\Jobs\RunEvaluation;
use App\Models\ConfidencePolicy;
use App\Models\EvaluationDataset;
use App\Models\EvaluationRun;
use App\Models\ModelVersion;
use App\Models\PromptVersion;
use App\Models\User;
use Illuminate\Console\Command;

class QueueEvaluationRun extends Command
{
    protected $signature = 'agroaide:evaluation:run {dataset} {--staff=} {--model=} {--prompt=} {--policy=}';

    protected $description = 'Queue a reproducible evaluation run against locked inputs and versions';

    public function handle(): int
    {
        $admin = User::where('email', $this->option('staff'))->first();
        $dataset = EvaluationDataset::find($this->argument('dataset'));
        if (! $admin?->isAdmin() || ! $dataset?->locked_at) {
            $this->error('An admin account and locked dataset are required.');

            return self::FAILURE;
        }
        $model = $this->option('model') ? ModelVersion::find($this->option('model')) : ModelVersion::where('active', true)->latest('id')->first();
        $prompt = $this->option('prompt') ? PromptVersion::find($this->option('prompt')) : PromptVersion::where('active', true)->latest('id')->first();
        $policy = $this->option('policy') ? ConfidencePolicy::find($this->option('policy')) : ConfidencePolicy::where('active', true)->latest('id')->first();
        if (! $model || ! $prompt || ! $policy) {
            $this->error('Model, prompt, and confidence-policy versions are required.');

            return self::FAILURE;
        }
        $run = EvaluationRun::create([
            'evaluation_dataset_id' => $dataset->id,
            'model_version_id' => $model->id, 'prompt_version_id' => $prompt->id,
            'confidence_policy_id' => $policy->id, 'created_by' => $admin->id,
            'status' => 'queued', 'sample_count' => $dataset->items()->count(),
        ]);
        RunEvaluation::dispatch($run->id)->onQueue('evaluation');
        $this->info("Queued evaluation run {$run->id}.");

        return self::SUCCESS;
    }
}
