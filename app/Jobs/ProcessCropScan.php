<?php

namespace App\Jobs;

use App\Models\FarmImageAnalysis;
use App\Services\CropDiagnosisService;
use App\Services\DiseaseOutbreakService;
use App\Services\ScanVerificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessCropScan implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $scanId) {}

    public function handle(
        CropDiagnosisService $diagnosis,
        ScanVerificationService $verification,
        DiseaseOutbreakService $outbreaks,
    ): void {
        $scan = FarmImageAnalysis::with(['user', 'farmField'])->findOrFail($this->scanId);
        if ($scan->processing_state === 'completed') {
            return;
        }

        $jobRunId = DB::table('system_job_runs')->insertGetId([
            'job_type' => self::class, 'reference_type' => FarmImageAnalysis::class,
            'reference_id' => $scan->id, 'status' => 'running', 'started_at' => now(),
            'heartbeat_at' => now(), 'attempt' => $this->attempts(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $scan->update(['processing_state' => 'processing', 'processing_started_at' => now(), 'safe_error_code' => null]);

        try {
            [$bytes, $mime] = $this->loadImageBytes($scan);
            $result = $diagnosis->diagnose(
                "data:{$mime};base64,".base64_encode($bytes),
                array_filter([
                    'crop' => $scan->farmField?->crop,
                    'language' => $scan->user?->preferred_language ?? 'en',
                    'latitude' => $scan->latitude ?? $scan->user?->farm_latitude,
                    'longitude' => $scan->longitude ?? $scan->user?->farm_longitude,
                ], static fn ($v) => $v !== null && $v !== ''),
            );
            $state = $verification->initialState(
                $result['confidence'],
                $result['canonical_valid'],
                (bool) ($result['research_backed'] ?? false),
            );
            $condition = strtolower((string) ($result['parsed']['condition'] ?? 'unknown'));
            $diseaseName = data_get($result, 'parsed.disease.name');
            $eligible = in_array($state, ['auto_verified', 'expert_verified'], true)
                && $result['disease_label_id'] !== null;

            $scan->update([
                'condition' => $condition,
                'disease_name' => $diseaseName,
                'result_json' => $result['parsed'],
                'raw_result' => $result['raw'],
                'raw_result_checksum' => $result['raw_checksum'],
                'model_version_id' => $result['model_version_id'],
                'prompt_version_id' => $result['prompt_version_id'],
                'confidence_policy_id' => $result['confidence_policy_id'],
                'predicted_crop_label_id' => $result['crop_label_id'],
                'predicted_disease_label_id' => $result['disease_label_id'],
                'effective_crop_label_id' => $result['crop_label_id'],
                'effective_disease_label_id' => $result['disease_label_id'],
                'normalized_confidence' => $result['confidence'],
                'inference_latency_ms' => $result['latency_ms'],
                'processing_state' => 'completed',
                'verification_state' => $state,
                'outbreak_eligible' => $eligible,
                'processing_completed_at' => now(),
            ]);
            if ($eligible) {
                if ($scan->farmField) {
                    $health = [
                        'healthy' => 95, 'good' => 85, 'fair' => 65,
                        'poor' => 40, 'diseased' => 30, 'critical' => 15,
                    ][$condition] ?? null;
                    if ($health !== null) {
                        $scan->farmField->update(['health_percentage' => $health]);
                    }
                }
                $outbreaks->checkForOutbreak($scan->refresh());
            }
            DB::table('system_job_runs')->where('id', $jobRunId)->update([
                'status' => 'completed', 'heartbeat_at' => now(), 'finished_at' => now(), 'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            $safeCode = match (true) {
                str_contains($e->getMessage(), 'provider') => 'provider_unavailable',
                str_contains($e->getMessage(), 'parse') => 'analysis_parse_failed',
                default => 'processing_failed',
            };
            $scan->update([
                'processing_state' => 'failed',
                'verification_state' => 'needs_retake',
                'outbreak_eligible' => false,
                'safe_error_code' => $safeCode,
                'processing_completed_at' => now(),
            ]);
            DB::table('system_job_runs')->where('id', $jobRunId)->update([
                'status' => 'failed',
                'heartbeat_at' => now(),
                'finished_at' => now(),
                'safe_error_code' => $safeCode,
                'safe_metadata' => json_encode([
                    'exception' => class_basename($e),
                    'message' => substr($e->getMessage(), 0, 180),
                    'previous' => $e->getPrevious() ? substr($e->getPrevious()->getMessage(), 0, 180) : null,
                ]),
                'updated_at' => now(),
            ]);
            throw $e;
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function loadImageBytes(FarmImageAnalysis $scan): array
    {
        if ($scan->image_url) {
            $response = Http::timeout(30)->get($scan->image_url);
            if (! $response->successful()) {
                throw new \RuntimeException('Could not download scan image from Cloudinary.');
            }
            $bytes = $response->body();
            $mime = $response->header('Content-Type') ?: 'image/jpeg';

            return [$bytes, $mime];
        }

        if (! $scan->image_path) {
            throw new \RuntimeException('Scan has no image path or Cloudinary URL.');
        }

        $bytes = Storage::disk('local')->get($scan->image_path);
        $mime = Storage::disk('local')->mimeType($scan->image_path) ?: 'image/jpeg';

        return [$bytes, $mime];
    }
}
