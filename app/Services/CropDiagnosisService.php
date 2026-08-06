<?php

namespace App\Services;

use App\Models\ConfidencePolicy;
use App\Models\ModelVersion;
use App\Models\PromptVersion;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CropDiagnosisService
{
    public function __construct(private CanonicalLabelResolver $labels) {}

    public function diagnose(string $imageDataUrl, array $context = [], array $versions = []): array
    {
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
        $response = Http::timeout(90)
            ->withToken((string) config('services.github_models.api_key'))
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => config('services.github_models.api_version', '2022-11-28'),
            ])->post(config('services.github_models.endpoint'), [
                'model' => $model->model_identifier,
                'messages' => [
                    ['role' => 'system', 'content' => $prompt->system_prompt],
                    ['role' => 'user', 'content' => [
                        ['type' => 'text', 'text' => $prompt->user_prompt."\nContext: ".json_encode($context)],
                        ['type' => 'image_url', 'image_url' => ['url' => $imageDataUrl]],
                    ]],
                ],
                ...$model->parameters,
            ]);
        $latency = (int) round((hrtime(true) - $started) / 1_000_000);
        if (! $response->successful()) {
            throw new RuntimeException('diagnosis_provider_error');
        }

        $raw = (string) data_get($response->json(), 'choices.0.message.content', '');
        $clean = trim(preg_replace('/^```(?:json)?|```$/m', '', $raw) ?? $raw);
        $parsed = json_decode($clean, true);
        if (! is_array($parsed)) {
            throw new RuntimeException('diagnosis_parse_error');
        }

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
}
