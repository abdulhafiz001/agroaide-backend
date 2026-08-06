<?php

namespace App\Services;

use App\Models\FarmField;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InputEstimateService
{
    /**
     * @return array<string, mixed>
     */
    public function estimate(FarmField $field, User $user, ?float $rowCm = null, ?float $intraCm = null, string $spacingMode = 'cm'): array
    {
        $cropKey = $this->normalizeCrop($field->crop);
        $table = config('input_rates.crops.'.$cropKey) ?? config('input_rates.default');
        $stepCm = (float) config('input_rates.step_cm', 75);

        $row = $rowCm ?? (float) ($table['defaultRowCm'] ?? 75);
        $intra = $intraCm ?? (float) ($table['defaultIntraCm'] ?? 25);
        $row = max(5.0, $row);
        $intra = max(5.0, $intra);

        $areaM2 = (float) ($field->area_m2 ?? 0);
        $areaSource = ! empty($field->boundary_geojson) ? 'measured' : 'estimate';
        if ($areaM2 <= 0) {
            $areaM2 = 1000.0;
            $areaSource = 'fallback';
        }

        $rowM = $row / 100.0;
        $intraM = $intra / 100.0;
        $population = (int) round($areaM2 / ($rowM * $intraM));
        $seedsPerStand = (int) ($table['seedsPerStand'] ?? 1);
        $seedsPerKg = max(1, (int) ($table['seedsPerKg'] ?? 5000));
        $seedUnit = $table['seedUnit'] ?? 'kg';

        $totalSeeds = $population * $seedsPerStand;
        $seedKg = $seedUnit === 'kg' ? round($totalSeeds / $seedsPerKg, 2) : null;
        $seedStands = $seedUnit !== 'kg' ? $population : null;

        $hectares = $areaM2 / 10000.0;
        $fertilizers = [];
        foreach ($table['fertilizers'] ?? [] as $f) {
            $kg = round($hectares * (float) ($f['kgPerHa'] ?? 0), 2);
            $fertilizers[] = [
                'name' => $f['name'],
                'kg' => $kg,
                'bags50kg' => round($kg / 50, 2),
                'kgPerHa' => (float) ($f['kgPerHa'] ?? 0),
            ];
        }

        $numbers = [
            'crop' => $cropKey,
            'areaM2' => round($areaM2, 2),
            'areaSource' => $areaSource,
            'spacingMode' => $spacingMode,
            'rowCm' => $row,
            'intraCm' => $intra,
            'rowSteps' => round($row / $stepCm, 2),
            'intraSteps' => round($intra / $stepCm, 2),
            'stepCm' => $stepCm,
            'population' => $population,
            'seedsPerStand' => $seedsPerStand,
            'seedUnit' => $seedUnit,
            'seedKg' => $seedKg,
            'seedStands' => $seedStands,
            'fertilizers' => $fertilizers,
            'disclaimer' => 'Guide only — may not be 100% correct for your soil and variety.',
        ];

        // Numbers always return immediately. AI rewrite is optional and must not block Calculate.
        $numbers['aiSummary'] = $this->buildFallbackSummary($user, $numbers);
        try {
            $ai = $this->summarizeWithAi($user, $numbers);
            if (is_string($ai) && trim($ai) !== '') {
                $numbers['aiSummary'] = $ai;
            }
        } catch (\Throwable $e) {
            Log::warning('InputEstimate AI skipped', ['message' => $e->getMessage()]);
        }

        return $numbers;
    }

    /**
     * @param  array<string, mixed>  $numbers
     */
    private function buildFallbackSummary(User $user, array $numbers): string
    {
        $fertLines = collect($numbers['fertilizers'] ?? [])
            ->map(fn ($f) => "{$f['name']}: {$f['kg']} kg (~{$f['bags50kg']} bags of 50kg)")
            ->implode('; ');

        $seedLine = $numbers['seedUnit'] === 'kg'
            ? "Seed: about {$numbers['seedKg']} kg"
            : 'Planting material: about '.($numbers['seedStands'] ?? $numbers['population']).' '.$numbers['seedUnit'];

        return "For your {$numbers['areaM2']} m² {$numbers['crop']} field, plant about {$numbers['rowCm']} cm × {$numbers['intraCm']} cm apart (~{$numbers['population']} stands). {$seedLine}. Fertilizer: {$fertLines}. ".$numbers['disclaimer'];
    }

    /**
     * @param  array<string, mixed>  $numbers
     */
    private function summarizeWithAi(User $user, array $numbers): string
    {
        $lang = $user->preferred_language ?? 'en';
        $langName = TranslationService::languageName($lang);
        $fallback = $this->buildFallbackSummary($user, $numbers);

        $apiKey = trim(config('services.github_models.api_key', ''));
        if ($apiKey === '') {
            return $fallback;
        }

        $prompt = 'Rewrite these farm input numbers into 3-5 short friendly sentences for a Nigerian farmer. '
            ."Use ONLY these numbers — do not invent different amounts. Language: {$langName}. "
            ."Include the disclaimer that it is a guide and may not be 100% correct.\n\n"
            .json_encode($numbers, JSON_PRETTY_PRINT);

        $endpoint = trim(config('services.github_models.endpoint', 'https://models.github.ai/inference/chat/completions'));
        $model = trim(config('services.github_models.model', 'openai/gpt-4o-mini'));
        $apiVersion = trim(config('services.github_models.api_version', '2022-11-28'));

        $response = Http::timeout(3)
            ->connectTimeout(2)
            ->withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => $apiVersion,
                'Content-Type' => 'application/json',
            ])
            ->post($endpoint, [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You write short agronomy summaries. Never change numeric quantities.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 280,
                'temperature' => 0.3,
            ]);

        if ($response->successful()) {
            $content = trim((string) ($response->json('choices.0.message.content') ?? ''));
            if ($content !== '') {
                return $content;
            }
        }

        return $fallback;
    }

    private function normalizeCrop(string $crop): string
    {
        $crops = array_keys(config('input_rates.crops', []));
        foreach ($crops as $known) {
            if (strcasecmp($known, $crop) === 0) {
                return $known;
            }
        }

        return $crop !== '' ? ucfirst(strtolower(trim($crop))) : 'Maize';
    }
}
