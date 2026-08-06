<?php

namespace App\Services;

use App\Models\ConfidencePolicy;
use App\Models\ModelVersion;
use App\Models\PromptVersion;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CropDiagnosisService
{
    public function __construct(
        private CanonicalLabelResolver $labels,
        private LlmChatClient $llm,
        private DiagnosisResponseParser $parser,
    ) {}

    public function diagnose(string $imageDataUrl, array $context = [], array $versions = []): array
    {
        $this->ensureDomainReady();

        $model = isset($versions['model_version_id'])
            ? ModelVersion::findOrFail($versions['model_version_id'])
            : ModelVersion::where('active', true)->latest('id')->firstOrFail();
        $prompt = isset($versions['prompt_version_id'])
            ? PromptVersion::findOrFail($versions['prompt_version_id'])
            : PromptVersion::where('active', true)->latest('id')->firstOrFail();
        $policy = isset($versions['confidence_policy_id'])
            ? ConfidencePolicy::findOrFail($versions['confidence_policy_id'])
            : ConfidencePolicy::where('active', true)->latest('id')->firstOrFail();

        $started = hrtime(true);
        $messages = [
            ['role' => 'system', 'content' => $prompt->system_prompt],
            ['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => $prompt->user_prompt."\nContext: ".json_encode($context)],
                ['type' => 'image_url', 'image_url' => ['url' => $imageDataUrl]],
            ]],
        ];

        try {
            $raw = $this->llm->chat($messages, array_merge([
                'timeout' => 90,
                'temperature' => 0.0,
                'max_tokens' => 2048,
            ], is_array($model->parameters) ? $model->parameters : []));
        } catch (\Throwable $e) {
            Log::error('Crop diagnosis provider failed', ['error' => $e->getMessage()]);
            throw new RuntimeException('diagnosis_provider_error', 0, $e);
        }

        $latency = (int) round((hrtime(true) - $started) / 1_000_000);

        try {
            $parsed = $this->parser->parse($raw);
        } catch (\Throwable $e) {
            Log::error('Crop diagnosis parse failed', [
                'error' => $e->getMessage(),
                'raw_prefix' => substr($raw, 0, 280),
            ]);
            throw new RuntimeException('diagnosis_parse_error', 0, $e);
        }

        unset($parsed['_raw_fallback']);

        $crop = $this->labels->resolve($parsed['crop'] ?? null, 'crop');
        $diseaseName = data_get($parsed, 'disease.name');
        $disease = $this->labels->resolve($diseaseName, 'disease');
        $confidence = max(0.0, min(1.0, ((float) ($parsed['confidencePercent'] ?? 0)) / 100));

        return [
            'parsed' => $parsed,
            'raw' => $raw,
            'raw_checksum' => hash('sha256', $raw),
            'model_version_id' => $model->id,
            'prompt_version_id' => $prompt->id,
            'confidence_policy_id' => $policy->id,
            'crop_label_id' => $crop?->id,
            'disease_label_id' => $disease?->id,
            'canonical_valid' => $crop !== null && (($parsed['condition'] ?? null) !== 'diseased' || $disease?->is_diseased),
            'confidence' => $confidence,
            'latency_ms' => $latency,
        ];
    }

    /**
     * Production deploys may skip DatabaseSeeder; still need model/prompt/policy rows.
     */
    private function ensureDomainReady(): void
    {
        $promptCfg = config('diagnosis.prompt');
        $ready = ModelVersion::where('active', true)->exists()
            && ConfidencePolicy::where('active', true)->exists()
            && PromptVersion::query()
                ->where('name', $promptCfg['name'])
                ->where('version', $promptCfg['version'])
                ->where('active', true)
                ->exists();

        if ($ready) {
            return;
        }

        Log::warning('Diagnosis domain incomplete or outdated — running DiagnosisDomainSeeder');
        try {
            (new \Database\Seeders\DiagnosisDomainSeeder)->run();
        } catch (\Throwable $e) {
            Log::error('DiagnosisDomainSeeder failed', ['error' => $e->getMessage()]);
            // Continue if an older active set already exists; otherwise diagnose() will fail clearly.
        }
    }
}
