<?php

namespace App\Jobs;

use App\Models\CanonicalLabel;
use App\Models\EvaluationRun;
use App\Services\CropDiagnosisService;
use App\Services\EvaluationMetricsCalculator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RunEvaluation implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public int $runId) {}

    public function handle(CropDiagnosisService $diagnosis, EvaluationMetricsCalculator $calculator): void
    {
        $run = EvaluationRun::findOrFail($this->runId);
        if ($run->status === 'completed') {
            return;
        }
        $run->update(['status' => 'running', 'started_at' => now(), 'safe_error_code' => null]);
        $items = DB::table('evaluation_dataset_items')
            ->where('evaluation_dataset_id', $run->evaluation_dataset_id)->orderBy('id')->get();
        $labels = CanonicalLabel::whereIn('id', $items->pluck('disease_label_id')->filter()->unique())->get()->keyBy('id');
        $rows = [];

        try {
            foreach ($items as $item) {
                $bytes = Storage::disk('local')->get($item->image_path);
                $mime = Storage::disk('local')->mimeType($item->image_path) ?: 'image/jpeg';
                // Ground truth is deliberately never included in context or prompts.
                $result = $diagnosis->diagnose("data:{$mime};base64,".base64_encode($bytes), [], [
                    'model_version_id' => $run->model_version_id,
                    'prompt_version_id' => $run->prompt_version_id,
                    'confidence_policy_id' => $run->confidence_policy_id,
                ]);
                $predictedId = $result['disease_label_id'];
                if ($predictedId === null && ($result['parsed']['condition'] ?? null) === 'healthy') {
                    $predictedId = CanonicalLabel::where('slug', 'healthy')->value('id');
                }
                $truth = $labels[$item->disease_label_id]?->slug;
                $prediction = $predictedId ? CanonicalLabel::whereKey($predictedId)->value('slug') : null;
                DB::table('evaluation_predictions')->insert([
                    'evaluation_run_id' => $run->id, 'evaluation_dataset_item_id' => $item->id,
                    'predicted_crop_label_id' => $result['crop_label_id'],
                    'predicted_disease_label_id' => $predictedId,
                    'normalized_confidence' => $result['confidence'],
                    'abstained' => $prediction === null, 'latency_ms' => $result['latency_ms'],
                    'raw_result' => $result['raw'], 'raw_result_checksum' => $result['raw_checksum'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $rows[] = ['truth' => $truth, 'prediction' => $prediction, 'latency_ms' => $result['latency_ms']];
            }
            $metrics = $calculator->calculate($rows, array_values(array_filter($labels->pluck('slug')->all())));
            foreach ($metrics['classes'] as $slug => $class) {
                $labelId = CanonicalLabel::where('slug', $slug)->value('id');
                if (! $labelId) {
                    continue;
                }
                DB::table('evaluation_class_metrics')->insert([
                    'evaluation_run_id' => $run->id, 'canonical_label_id' => $labelId,
                    'tp' => $class['tp'], 'fp' => $class['fp'], 'fn' => $class['fn'], 'tn' => $class['tn'],
                    'precision' => $class['precision'], 'recall' => $class['recall'],
                    'f1' => $class['f1'], 'fpr' => $class['fpr'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $run->update([
                'status' => 'completed', 'sample_count' => count($rows),
                'metrics' => $metrics, 'completed_at' => now(),
            ]);
        } catch (Throwable $e) {
            $run->update(['status' => 'failed', 'safe_error_code' => 'evaluation_failed', 'completed_at' => now()]);
            throw $e;
        }
    }
}
