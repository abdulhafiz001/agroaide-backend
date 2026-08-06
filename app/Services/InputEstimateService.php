<?php

namespace App\Services;

use App\Models\FarmField;
use App\Models\InputEstimateHistory;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class InputEstimateService
{
    public function __construct(
        private LlmChatClient $llm,
        private LlmResponseCleaner $cleaner,
    ) {}

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

        $numbers['aiSummary'] = $this->buildFallbackSummary($user, $numbers);
        try {
            $ai = $this->summarizeWithAi($user, $numbers);
            if (is_string($ai) && trim($ai) !== '') {
                $numbers['aiSummary'] = $ai;
            }
        } catch (\Throwable $e) {
            Log::warning('InputEstimate AI skipped', ['message' => $e->getMessage()]);
        }

        $history = InputEstimateHistory::create([
            'user_id' => $user->id,
            'farm_field_id' => $field->id,
            'crop' => $cropKey,
            'area_m2' => $numbers['areaM2'],
            'row_cm' => $row,
            'intra_cm' => $intra,
            'population' => $population,
            'result_json' => $numbers,
            'ai_summary' => $numbers['aiSummary'],
        ]);
        $numbers['historyId'] = (string) $history->id;
        $numbers['savedAt'] = optional($history->created_at)?->toIso8601String();

        return $numbers;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function historyForField(User $user, FarmField $field, int $limit = 30): array
    {
        return InputEstimateHistory::query()
            ->where('user_id', $user->id)
            ->where('farm_field_id', $field->id)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (InputEstimateHistory $row) => $this->transformHistory($row))
            ->all();
    }

    public function deleteHistory(User $user, int $historyId): bool
    {
        $row = InputEstimateHistory::query()
            ->where('user_id', $user->id)
            ->where('id', $historyId)
            ->first();

        if (! $row) {
            return false;
        }

        $row->delete();

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function transformHistory(InputEstimateHistory $row): array
    {
        $result = is_array($row->result_json) ? $row->result_json : [];

        return [
            'id' => (string) $row->id,
            'fieldId' => (string) $row->farm_field_id,
            'crop' => $row->crop,
            'areaM2' => $row->area_m2,
            'rowCm' => $row->row_cm,
            'intraCm' => $row->intra_cm,
            'population' => $row->population,
            'aiSummary' => $row->ai_summary ?: ($result['aiSummary'] ?? null),
            'estimate' => $result,
            'date' => optional($row->created_at)?->toIso8601String(),
        ];
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
        $user->refresh();
        $lang = $user->preferred_language ?? 'en';
        $langName = TranslationService::languageName($lang);
        $fallback = $this->buildFallbackSummary($user, $numbers);

        $prompt = "Write 3-5 short friendly sentences in {$langName} for a Nigerian farmer. "
            .'Use ONLY these numbers — never invent different amounts. '
            .'Do NOT include thinking, analysis, checklists, or phrases like "thinking process". '
            .'Output the farmer sentences only.'."\n\n"
            .json_encode($numbers, JSON_PRETTY_PRINT);

        try {
            $content = $this->cleaner->clean($this->llm->chat([
                [
                    'role' => 'system',
                    'content' => 'You write short agronomy summaries for farmers. Never change numeric quantities. Never show thinking. Reply with the final farmer-facing sentences only.',
                ],
                ['role' => 'user', 'content' => $prompt],
            ], [
                'timeout' => 12,
                'max_tokens' => 320,
                'temperature' => 0.2,
            ]));

            if ($content === '' || preg_match('/thinking process|analyze user input/i', $content)) {
                return $fallback;
            }

            return $content;
        } catch (\Throwable $e) {
            Log::debug('Input estimate AI summary skipped', ['error' => $e->getMessage()]);

            return $fallback;
        }
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
