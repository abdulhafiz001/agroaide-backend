<?php

namespace App\Services;

use App\Models\ConfidencePolicy;
use App\Models\ModelVersion;
use App\Models\PromptVersion;
use Database\Seeders\DiagnosisDomainSeeder;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CropDiagnosisService
{
    public function __construct(
        private CanonicalLabelResolver $labels,
        private KindwiseCropHealthClient $kindwise,
        private LlmChatClient $llm,
        private DiagnosisResponseParser $parser,
        private LlmResponseCleaner $cleaner,
    ) {}

    /**
     * @param  array{crop?:string,language?:string,latitude?:float,longitude?:float}  $context
     * @param  array{model_version_id?:int,prompt_version_id?:int,confidence_policy_id?:int}  $versions
     */
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
        $language = (string) ($context['language'] ?? 'en');

        try {
            $kindwise = $this->kindwise->identify($imageDataUrl, array_filter([
                'language' => $language,
                'latitude' => $context['latitude'] ?? null,
                'longitude' => $context['longitude'] ?? null,
            ], static fn ($v) => $v !== null && $v !== ''));
        } catch (\Throwable $e) {
            Log::error('Kindwise crop identification failed', ['error' => $e->getMessage()]);
            throw new RuntimeException('diagnosis_provider_error', 0, $e);
        }

        $rawKindwise = json_encode($kindwise['raw'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';

        if (! ($kindwise['is_crop'] ?? false)) {
            $parsed = $this->nonCropResult($language);
            $latency = (int) round((hrtime(true) - $started) / 1_000_000);

            return [
                'parsed' => $parsed,
                'raw' => $rawKindwise."\n---\n".json_encode($parsed),
                'raw_checksum' => hash('sha256', $rawKindwise),
                'model_version_id' => $model->id,
                'prompt_version_id' => $prompt->id,
                'confidence_policy_id' => $policy->id,
                'crop_label_id' => null,
                'disease_label_id' => null,
                'canonical_valid' => false,
                // High enough to complete (not "failed"), while UI shows unknown / not-a-crop.
                'confidence' => 0.55,
                'latency_ms' => $latency,
                'research_backed' => true,
            ];
        }

        $farmerJson = $this->explainWithGemini($kindwise, $context, $language, $prompt);
        $latency = (int) round((hrtime(true) - $started) / 1_000_000);

        try {
            $parsed = $this->parser->parse($farmerJson);
        } catch (\Throwable $e) {
            Log::warning('Gemini scan write-up parse failed; building from Kindwise', [
                'error' => $e->getMessage(),
            ]);
            $parsed = $this->parser->normalize($this->fromKindwiseFallback($kindwise), $farmerJson);
        }

        // Prefer Kindwise identity fields when Gemini drifts.
        if (! empty($kindwise['crop']['name'])) {
            $parsed['crop'] = $kindwise['crop']['name'];
        }
        if (! empty($kindwise['disease']['name'])) {
            $parsed['disease'] = is_array($parsed['disease'] ?? null)
                ? array_merge($parsed['disease'], ['name' => $kindwise['disease']['name']])
                : ['name' => $kindwise['disease']['name'], 'scientificName' => '', 'symptoms' => [], 'cause' => '', 'severity' => 'moderate', 'spreadRisk' => 'medium'];
        } elseif ($kindwise['is_healthy']) {
            $parsed['disease'] = null;
            if (! in_array($parsed['condition'] ?? '', ['healthy', 'good'], true)) {
                $parsed['condition'] = 'healthy';
                $parsed['conditionLabel'] = 'Healthy';
            }
        }

        // Never show a disease card when condition is unknown / not a crop.
        if (($parsed['condition'] ?? '') === 'unknown') {
            $parsed['disease'] = null;
        }

        $isHealthyResult = ! empty($kindwise['is_healthy'])
            || in_array($parsed['condition'] ?? '', ['healthy', 'good'], true);
        if ($isHealthyResult) {
            $parsed['disease'] = null;
            $parsed['recommendations'] = [
                'immediate' => [],
                'products' => [],
                'prevention' => [],
                'longTerm' => [],
            ];
            // If Gemini still returns a tiny summary, expand with Kindwise-backed copy.
            if (str_word_count((string) ($parsed['summary'] ?? '')) < 28) {
                $fallback = $this->fromKindwiseFallback($kindwise);
                $parsed['summary'] = $fallback['summary'];
                if (str_word_count((string) ($parsed['personalizedNote'] ?? '')) < 12) {
                    $parsed['personalizedNote'] = $fallback['personalizedNote'];
                }
            }
        }

        $confidencePercent = (int) round(max(0, min(1, (float) $kindwise['confidence'])) * 100);
        if ($confidencePercent > 0) {
            $parsed['confidencePercent'] = $confidencePercent;
        }
        $parsed['source'] = 'kindwise';
        $parsed['researchBacked'] = true;

        $crop = $this->labels->resolve($parsed['crop'] ?? null, 'crop');
        $diseaseName = data_get($parsed, 'disease.name');
        $disease = $this->labels->resolve($diseaseName, 'disease');
        $confidence = max(0.0, min(1.0, ((float) ($parsed['confidencePercent'] ?? 0)) / 100));

        return [
            'parsed' => $parsed,
            'raw' => $rawKindwise."\n---\n".$farmerJson,
            'raw_checksum' => hash('sha256', $rawKindwise."\n---\n".$farmerJson),
            'model_version_id' => $model->id,
            'prompt_version_id' => $prompt->id,
            'confidence_policy_id' => $policy->id,
            'crop_label_id' => $crop?->id,
            'disease_label_id' => $disease?->id,
            'canonical_valid' => true,
            'confidence' => $confidence > 0 ? $confidence : (float) $kindwise['confidence'],
            'latency_ms' => $latency,
            'research_backed' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function nonCropResult(string $language): array
    {
        $copy = match ($language) {
            'ha' => [
                'summary' => 'Wannan hoton ba na amfanin gona ba ne. Da fatan za a ɗauki kusa da ganye ko shuka.',
                'note' => 'Don ganewa mai kyau, ɗauki hoton shuka a haske mai kyau.',
                'immediate' => ['Ƙara ɗaukar hoton ganye ko shuka', 'Tabbatar da haske yayi kyau'],
            ],
            'yo' => [
                'summary' => 'Aworan yii kii se ohun ogbin. Jowo ya aworan ewe tabi irugbin.',
                'note' => 'Fun abajade to dara, ya aworan ogbin ni ina to dara.',
                'immediate' => ['Ya aworan ewe tabi ogbin', 'Rii daju pe ina to dara'],
            ],
            'pcm' => [
                'summary' => 'This photo no be crop/plant. Abeg snap leaf or crop wey clear.',
                'note' => 'For better result, take clear photo of your crop for good light.',
                'immediate' => ['Snap clear crop or leaf photo', 'Make sure light dey okay'],
            ],
            default => [
                'summary' => 'This does not look like a crop or plant photo. Please take a clear picture of leaves or crops in the field.',
                'note' => 'For a useful diagnosis, photograph the plant (close-up of leaves) in good light.',
                'immediate' => ['Take a clear photo of the crop or leaves', 'Use good lighting and avoid blur'],
            ],
        };

        return [
            'crop' => null,
            'condition' => 'unknown',
            'conditionLabel' => 'Not a crop photo',
            'confidencePercent' => 10,
            'summary' => $copy['summary'],
            'details' => null,
            'disease' => null,
            'recommendations' => [
                'immediate' => $copy['immediate'],
                'products' => [],
                'prevention' => [],
                'longTerm' => [],
            ],
            'personalizedNote' => $copy['note'],
            'source' => 'kindwise',
            'researchBacked' => true,
            'isCrop' => false,
        ];
    }

    /**
     * @param  array{crop:?array,disease:?array,is_healthy:bool,confidence:float,raw:array}  $kindwise
     * @param  array{crop?:string}  $context
     */
    private function explainWithGemini(array $kindwise, array $context, string $language, PromptVersion $prompt): string
    {
        $langName = match ($language) {
            'ha' => 'Hausa',
            'yo' => 'Yoruba',
            'pcm' => 'Nigerian Pidgin',
            default => 'English',
        };

        $cropDetails = is_array($kindwise['crop']['details'] ?? null) ? $kindwise['crop']['details'] : [];
        $diseaseDetails = is_array(data_get($kindwise, 'disease.details')) ? data_get($kindwise, 'disease.details') : [];

        $evidence = [
            'farmCropHint' => $context['crop'] ?? null,
            'kindwiseCrop' => $kindwise['crop'],
            'kindwiseDisease' => $kindwise['disease'],
            'isHealthy' => $kindwise['is_healthy'],
            'isCrop' => $kindwise['is_crop'] ?? true,
            'confidence' => $kindwise['confidence'],
            'kindwiseCropNotes' => [
                'commonNames' => $cropDetails['common_names'] ?? data_get($kindwise, 'crop.details.common_names'),
                'description' => data_get($cropDetails, 'description.value')
                    ?? data_get($cropDetails, 'wiki_description.value')
                    ?? data_get($cropDetails, 'description'),
            ],
            'kindwiseDiseaseNotes' => [
                'description' => data_get($diseaseDetails, 'description.value')
                    ?? data_get($diseaseDetails, 'wiki_description.value'),
                'symptoms' => $diseaseDetails['symptoms'] ?? null,
                'treatment' => $diseaseDetails['treatment'] ?? null,
                'severity' => $diseaseDetails['severity'] ?? null,
            ],
            'cropSuggestions' => array_slice(data_get($kindwise, 'raw.result.crop.suggestions', []) ?: [], 0, 3),
            'diseaseSuggestions' => array_slice(data_get($kindwise, 'raw.result.disease.suggestions', []) ?: [], 0, 3),
        ];

        $healthyHint = ! empty($kindwise['is_healthy'])
            ? ' This scan is HEALTHY: write a fuller 4-6 sentence summary and a 2-3 sentence personalizedNote using Kindwise crop notes. Leave recommendation arrays empty.'
            : ' This scan has a disease: fill recommendations for what the farmer should do.';

        $messages = [
            [
                'role' => 'system',
                'content' => $prompt->system_prompt."\n\nWrite every farmer-facing string in {$langName}. Never include thinking, analysis, or chain-of-thought — JSON only. If isCrop is false, set condition to unknown, disease to null, and explain that the photo is not a crop.{$healthyHint}",
            ],
            [
                'role' => 'user',
                'content' => $prompt->user_prompt."\n\nKindwise research evidence (use this as ground truth):\n".
                    json_encode($evidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
        ];

        try {
            return $this->cleaner->clean($this->llm->chat($messages, [
                'timeout' => 90,
                'temperature' => 0.2,
                'max_tokens' => 2048,
            ]));
        } catch (\Throwable $e) {
            Log::warning('Gemini scan write-up failed; using Kindwise fallback text', [
                'error' => $e->getMessage(),
            ]);

            return json_encode($this->fromKindwiseFallback($kindwise), JSON_UNESCAPED_UNICODE) ?: '{}';
        }
    }

    /**
     * @param  array{crop:?array,disease:?array,is_healthy:bool,confidence:float}  $kindwise
     * @return array<string, mixed>
     */
    private function fromKindwiseFallback(array $kindwise): array
    {
        $cropName = (string) ($kindwise['crop']['name'] ?? 'Unknown crop');
        $disease = $kindwise['disease'];
        $healthy = (bool) $kindwise['is_healthy'] || $disease === null;
        $confidence = (int) round(((float) $kindwise['confidence']) * 100);
        $diseaseName = (string) ($disease['name'] ?? '');
        $details = is_array($disease['details'] ?? null) ? $disease['details'] : [];
        $symptoms = [];
        foreach ((array) ($details['symptoms'] ?? []) as $symptom) {
            if (is_string($symptom) && trim($symptom) !== '') {
                $symptoms[] = trim($symptom);
            } elseif (is_array($symptom)) {
                $symptoms[] = trim((string) ($symptom['description'] ?? $symptom['name'] ?? ''));
            }
        }
        $symptoms = array_values(array_filter($symptoms));

        $treatment = $details['treatment'] ?? [];
        $immediate = [];
        foreach (['biological', 'chemical', 'prevention'] as $key) {
            $value = $treatment[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $immediate[] = trim($value);
            } elseif (is_array($value)) {
                foreach ($value as $item) {
                    if (is_string($item) && trim($item) !== '') {
                        $immediate[] = trim($item);
                    }
                }
            }
        }
        if (! $healthy && $immediate === []) {
            $immediate = ['Isolate affected plants if practical', 'Ask a local extension officer before applying chemicals'];
        }

        $cropNote = (string) (
            data_get($kindwise, 'crop.details.description.value')
            ?? data_get($kindwise, 'crop.details.wiki_description.value')
            ?? ''
        );
        $summary = $healthy
            ? "Good news — your {$cropName} looks healthy. Kindwise crop.health did not find a disease on this photo, and the plant appearance matches a healthy crop. "
                .'Keep watching the leaves for early spots or yellowing, and maintain your usual watering and nutrition schedule. '
                .'If the field has been dry or very wet lately, check moisture at root level so the plants stay strong. '
                .($cropNote !== '' ? 'Note from the identification: '.mb_substr(trim($cropNote), 0, 180).' ' : '')
                .'Take another photo in a few days if anything changes.'
            : "{$diseaseName} detected on {$cropName} (crop.health identification).";

        return [
            'crop' => $cropName,
            'condition' => $healthy ? 'healthy' : 'diseased',
            'conditionLabel' => $healthy ? 'Healthy' : 'Diseased',
            'confidencePercent' => max(1, $confidence),
            'summary' => $summary,
            'details' => [
                'plantsVisible' => $cropName,
                'growthStage' => 'unknown',
                'overallVigor' => $healthy ? 'healthy' : 'stressed',
            ],
            'disease' => $healthy ? null : [
                'name' => $diseaseName,
                'scientificName' => (string) ($details['scientific_name'] ?? ''),
                'symptoms' => $symptoms,
                'cause' => (string) ($details['description']['value'] ?? $details['description'] ?? 'See treatment advice.'),
                'severity' => $this->mapSeverity((string) ($details['severity'] ?? 'moderate')),
                'spreadRisk' => 'medium',
            ],
            'recommendations' => [
                'immediate' => $healthy ? [] : array_slice($immediate, 0, 4),
                'products' => [],
                'prevention' => $healthy ? [] : array_values(array_filter([
                    is_string($treatment['prevention'] ?? null) ? trim((string) $treatment['prevention']) : null,
                ])),
                'longTerm' => [],
            ],
            'personalizedNote' => $healthy
                ? "Your {$cropName} field looks in good shape today. Keep up regular scouting and you will catch problems early if they appear."
                : 'This result is based on Kindwise crop.health research-backed identification.',
        ];
    }

    private function mapSeverity(string $value): string
    {
        $value = strtolower(trim($value));

        return match (true) {
            str_contains($value, 'severe') || str_contains($value, 'high') => 'severe',
            str_contains($value, 'mild') || str_contains($value, 'low') => 'mild',
            default => 'moderate',
        };
    }

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
            (new DiagnosisDomainSeeder)->run();
        } catch (\Throwable $e) {
            Log::error('DiagnosisDomainSeeder failed', ['error' => $e->getMessage()]);
        }
    }
}
