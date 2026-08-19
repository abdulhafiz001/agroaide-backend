<?php

namespace App\Services;

use App\Models\AdvisorConversation;
use App\Models\CalendarTask;
use App\Models\FarmField;
use App\Models\FarmImageAnalysis;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AiAdvisorService
{
    public function __construct(
        private WeatherService $weatherService,
        private LlmChatClient $llm,
    ) {}

    /**
     * Chat with the AI advisor, passing full user context.
     *
     * @param  string|null  $languageOverride  Request language (takes priority over stale in-memory user).
     */
    public function chat(User $user, string $message, ?string $languageOverride = null): string
    {
        $user->refresh();
        if (is_string($languageOverride) && $languageOverride !== '') {
            $lang = $languageOverride;
            if (($user->preferred_language ?? 'en') !== $lang) {
                $user->forceFill(['preferred_language' => $lang])->save();
            }
        } else {
            $lang = $user->preferred_language ?? 'en';
        }

        AdvisorConversation::create([
            'user_id' => $user->id,
            'role' => 'user',
            'message' => $message,
        ]);

        $langName = TranslationService::languageName($lang);
        $systemPrompt = $this->buildSystemPrompt($user, $lang);
        // Includes the user message just saved — do not append it again.
        $conversationHistory = $this->getRecentConversation($user, 24);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($conversationHistory as $msg) {
            $messages[] = [
                'role' => $msg->role === 'assistant' ? 'assistant' : 'user',
                'content' => $msg->message,
            ];
        }

        // Hard override after history so older messages cannot keep the previous language.
        $messages[] = [
            'role' => 'user',
            'content' => "SYSTEM REMINDER: Reply to my last farming question entirely in {$langName}. Do not use any other language. Do not show thinking.",
        ];

        $reply = app(LlmResponseCleaner::class)->clean($this->askLlm($messages));

        AdvisorConversation::create([
            'user_id' => $user->id,
            'role' => 'assistant',
            'message' => $reply,
        ]);

        return $reply;
    }

    /**
     * Return persisted conversation history for the mobile advisor screen.
     */
    public function history(User $user, int $limit = 60): array
    {
        return AdvisorConversation::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (AdvisorConversation $msg) => [
                'id' => (string) $msg->id,
                'text' => $msg->message,
                'fromAgent' => $msg->role === 'assistant',
                'timestamp' => optional($msg->created_at)?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Generate daily insight for a user (cached per user per day + weather fingerprint).
     * Regenerates when the calendar day changes or today's rain outlook shifts meaningfully.
     */
    public function dailyInsight(User $user): array
    {
        $user->refresh();
        $lang = $user->preferred_language ?? 'en';
        $pulse = $this->buildTodayFarmPulse($user);
        $day = now()->toDateString();
        $cacheKey = "daily_insight_{$user->id}_{$lang}_{$day}_{$pulse['fingerprint']}";

        return Cache::remember($cacheKey, now()->endOfDay(), function () use ($user, $lang, $pulse) {
            return $this->generateDailyInsight($user, $lang, $pulse);
        });
    }

    public static function forgetDailyInsightCache(int $userId): void
    {
        // Exact keys include a weather fingerprint; wipe known language+day prefixes via cache store tags is unavailable.
        // Forget stable day keys (legacy + language) so the next request rebuilds with a fresh fingerprint.
        $day = now()->toDateString();
        foreach (['en', 'ha', 'yo', 'pcm'] as $lang) {
            Cache::forget("daily_insight_{$userId}_{$lang}_".$day);
            Cache::forget("daily_insight_{$userId}_".$day);
        }
    }

    /**
     * Get personalized suggestion prompts based on user context.
     */
    public function getSuggestions(User $user): array
    {
        $crops = is_array($user->crops) ? $user->crops : [];
        $suggestions = [];

        if (! empty($crops)) {
            $suggestions[] = "How are my {$crops[0]} crops doing?";
        }
        $suggestions[] = 'What should I do on my farm today?';
        $suggestions[] = 'Is it going to rain this week?';

        if ($user->soil_type) {
            $suggestions[] = "Best fertilizer for {$user->soil_type} soil?";
        } else {
            $suggestions[] = 'Best fertilizer for my soil?';
        }

        return array_slice($suggestions, 0, 4);
    }

    /**
     * @param  array{summary:string,fingerprint:string,rainToday:bool,rainTonight:bool,todayPrecipMm:float,todayRainChance:int,tonightMaxChance:int,condition:string}  $pulse
     */
    private function generateDailyInsight(User $user, string $lang, array $pulse): array
    {
        $systemPrompt = $this->buildSystemPrompt($user, $lang);
        $langName = TranslationService::languageName($lang);
        $crops = is_array($user->crops) ? implode(', ', $user->crops) : 'your crops';

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => <<<PROMPT
TODAY'S FARM PULSE (must drive the insights — do not ignore):
{$pulse['summary']}

Give exactly 2 short, actionable farming insights for THIS farmer for TODAY.
Rules:
1. At least one insight MUST react to rain / no-rain today or tonight when rain data is present (delay spraying if rain is likely; irrigate if dry; protect harvested produce if overnight rain, etc.).
2. Tie advice to {$crops} and today's tasks/scans when available.
3. Write title and description in {$langName}.
4. Title max 8 words. Description max 35 words.
5. Return ONLY a valid JSON array: [{"title":"...","description":"..."}]
PROMPT],
        ];

        try {
            $reply = $this->askLlm($messages, ['temperature' => 0.35, 'max_tokens' => 512]);
            $cleaned = preg_replace('/```json\s*|\s*```/', '', $reply) ?? $reply;
            $cleaned = trim($cleaned);
            $parsed = json_decode($cleaned, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                $insights = [];
                foreach (array_slice($parsed, 0, 3) as $i => $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $insights[] = [
                        'id' => 'tip-'.($i + 1).'-'.now()->toDateString(),
                        'title' => $item['title'] ?? 'Farm tip',
                        'description' => $item['description'] ?? 'Check your crops and field conditions today.',
                    ];
                }
                if ($insights !== []) {
                    return $insights;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Daily insight LLM failed; using weather fallback', ['error' => $e->getMessage()]);
        }

        return $this->fallbackDailyInsights($pulse, $crops);
    }

    /**
     * Compact today/tonight weather + farm signals for dashboard insights.
     *
     * @return array{summary:string,fingerprint:string,rainToday:bool,rainTonight:bool,todayPrecipMm:float,todayRainChance:int,tonightMaxChance:int,condition:string}
     */
    private function buildTodayFarmPulse(User $user): array
    {
        $lines = [];
        $todayPrecipMm = 0.0;
        $todayRainChance = 0;
        $tonightMaxChance = 0;
        $condition = 'unknown';
        $rainToday = false;
        $rainTonight = false;

        if ($user->hasFarmCoordinates()) {
            try {
                $weather = $this->weatherService->getWeatherForUser($user) ?? [];
                $current = $weather['current'] ?? [];
                $condition = (string) ($current['condition'] ?? 'unknown');
                $temp = $current['temperature'] ?? 'n/a';
                $humidity = $current['humidity'] ?? 'n/a';
                $lines[] = "Now: {$temp}°C, {$condition}, humidity {$humidity}%.";

                $today = collect($weather['forecast'] ?? [])->firstWhere('day', 'Today')
                    ?? collect($weather['forecast'] ?? [])->first();
                if (is_array($today)) {
                    $todayPrecipMm = (float) ($today['precipitation'] ?? 0);
                    $todayRainChance = (int) ($today['precipitationProbability'] ?? 0);
                    $rainToday = $todayPrecipMm >= 0.5 || $todayRainChance >= 40;
                    $lines[] = sprintf(
                        'Today: %s, high %s° / low %s°, rain %s mm (~%s%% chance).',
                        $today['condition'] ?? $condition,
                        $today['high'] ?? 'n/a',
                        $today['low'] ?? 'n/a',
                        $todayPrecipMm,
                        $todayRainChance,
                    );
                }

                $hour = (int) now()->format('G');
                foreach ($weather['hourly'] ?? [] as $slot) {
                    $time = (string) ($slot['time'] ?? '');
                    if ($time === '' || ! str_contains($time, 'T')) {
                        continue;
                    }
                    $slotHour = (int) substr($time, 11, 2);
                    // Tonight window: from max(current hour, 18:00) through 05:00 next morning (within next 24h list).
                    $isEvening = $slotHour >= max($hour, 18) || $slotHour <= 5;
                    if (! $isEvening) {
                        continue;
                    }
                    $chance = (int) ($slot['precipitationProbability'] ?? 0);
                    $tonightMaxChance = max($tonightMaxChance, $chance);
                }
                $rainTonight = $tonightMaxChance >= 45;
                $lines[] = $rainTonight
                    ? "Tonight: rain likely (peak chance ~{$tonightMaxChance}%). Avoid late spraying; cover harvested produce."
                    : "Tonight: mostly dry (peak rain chance ~{$tonightMaxChance}%).";

                foreach (array_slice($weather['soilHealth'] ?? [], 0, 2) as $soil) {
                    $lines[] = ($soil['label'] ?? 'Soil').': '.($soil['value'] ?? 'n/a').($soil['unit'] ?? '');
                }
            } catch (\Throwable $e) {
                $lines[] = 'Weather temporarily unavailable.';
                Log::warning('Today farm pulse weather failed: '.$e->getMessage());
            }
        } else {
            $lines[] = 'No farm GPS — set location in Settings for rain-aware tips.';
        }

        $crops = is_array($user->crops) ? implode(', ', $user->crops) : '';
        if ($crops !== '') {
            $lines[] = "Crops: {$crops}.";
        }

        $pendingTasks = CalendarTask::where('user_id', $user->id)
            ->whereDate('scheduled_date', now()->toDateString())
            ->where('completed', false)
            ->orderBy('period')
            ->limit(3)
            ->pluck('title')
            ->all();
        if ($pendingTasks !== []) {
            $lines[] = 'Pending tasks today: '.implode('; ', $pendingTasks).'.';
        }

        $recentScan = FarmImageAnalysis::where('user_id', $user->id)
            ->where('processing_state', 'completed')
            ->where('created_at', '>=', now()->subDays(3))
            ->latest('id')
            ->first();
        if ($recentScan) {
            $disease = $recentScan->disease_name ?: 'no disease flagged';
            $lines[] = "Recent scan ({$recentScan->condition}): {$disease}.";
        }

        $fingerprint = substr(hash('sha256', implode('|', [
            now()->toDateString(),
            $condition,
            (string) $todayRainChance,
            (string) round($todayPrecipMm, 1),
            (string) $tonightMaxChance,
            $rainToday ? '1' : '0',
            $rainTonight ? '1' : '0',
        ])), 0, 12);

        return [
            'summary' => implode("\n", $lines),
            'fingerprint' => $fingerprint,
            'rainToday' => $rainToday,
            'rainTonight' => $rainTonight,
            'todayPrecipMm' => $todayPrecipMm,
            'todayRainChance' => $todayRainChance,
            'tonightMaxChance' => $tonightMaxChance,
            'condition' => $condition,
        ];
    }

    /**
     * @param  array{rainToday:bool,rainTonight:bool,todayPrecipMm:float,todayRainChance:int,tonightMaxChance:int,condition:string}  $pulse
     * @return array<int, array{id:string,title:string,description:string}>
     */
    private function fallbackDailyInsights(array $pulse, string $crops): array
    {
        $day = now()->toDateString();
        $cropLabel = $crops !== '' ? $crops : 'your crops';

        if ($pulse['rainTonight'] || $pulse['rainToday']) {
            return [
                [
                    'id' => "tip-1-{$day}",
                    'title' => 'Rain coming — plan around it',
                    'description' => sprintf(
                        'Rain chance ~%s%% today / ~%s%% tonight. Delay spraying %s; finish drainage checks before dusk.',
                        $pulse['todayRainChance'],
                        $pulse['tonightMaxChance'],
                        $cropLabel,
                    ),
                ],
                [
                    'id' => "tip-2-{$day}",
                    'title' => 'Protect harvested produce',
                    'description' => 'Cover bags and tools overnight. Walk fields after rain for lodging or new leaf spots.',
                ],
            ];
        }

        return [
            [
                'id' => "tip-1-{$day}",
                'title' => 'Dry window for field work',
                'description' => sprintf(
                    'Little rain expected (today ~%s%%). Good day to weed, spray if needed, or irrigate %s early morning.',
                    $pulse['todayRainChance'],
                    $cropLabel,
                ),
            ],
            [
                'id' => "tip-2-{$day}",
                'title' => 'Scout for pests at dawn',
                'description' => 'Dry mornings are ideal for leaf checks. Catch early damage before it spreads across the plot.',
            ],
        ];
    }

    private function buildSystemPrompt(User $user, string $lang = 'en'): string
    {
        $name = $user->name ?? 'Farmer';
        $farmName = $user->farm_name ?? 'the farm';
        $location = $user->farm_location ?? 'Nigeria';
        $crops = is_array($user->crops) ? implode(', ', $user->crops) : 'various crops';
        $soilType = $user->soil_type ?? 'unknown';
        $experience = $user->experience_level ?? 'beginner';
        $irrigation = $user->irrigation_access ?? 'rain-fed';
        $farmSize = $user->farm_size_m2 ?? 0;
        $lat = $user->farm_latitude;
        $lng = $user->farm_longitude;
        $today = now()->toDateString();
        $depth = $user->ai_response_depth ?? 'balanced';
        $risk = $user->ai_risk_tolerance ?? 'balanced';
        $depthInstruction = match ($depth) {
            'concise' => 'Answer in 1-2 short paragraphs or at most 4 bullets.',
            'deep' => 'Give a thorough explanation with reasoning, trade-offs, and ordered steps.',
            default => 'Give a practical answer in 2-4 short paragraphs with bullets when useful.',
        };
        $riskInstruction = match ($risk) {
            'cautious' => 'Prefer low-risk, reversible actions and clearly identify uncertainty before costly treatment.',
            'bold' => 'When evidence supports it, recommend decisive action while stating the main downside.',
            default => 'Balance likely benefits, cost, uncertainty, and reversibility.',
        };
        $weatherBlock = $this->buildWeatherContext($user);
        $fieldsBlock = $this->buildFieldsContext($user);
        $tasksBlock = $this->buildTasksContext($user);
        $journalBlock = $this->buildJournalContext($user);
        $scansBlock = $this->buildRecentScansContext($user);

        $prompt = <<<PROMPT
You are AgroAide AI, a personalized agricultural advisor embedded inside the AgroAide app for Nigerian farmers.
You are speaking with {$name}, who manages "{$farmName}" in {$location}.

TODAY'S DATE: {$today}
FARM PROFILE:
- Size: {$farmSize} square meters
- Crops: {$crops}
- Soil type: {$soilType}
- Irrigation: {$irrigation}
- Experience: {$experience}
- Coordinates: {$lat}, {$lng}

{$weatherBlock}

{$fieldsBlock}

{$tasksBlock}

{$journalBlock}

{$scansBlock}

CRITICAL RULES:
1. You already have live farm + weather + crop-scan data above. USE IT. When asked about rain, temperature, soil, tasks, fields, or a recent scan, answer from this context first.
2. Never say you lack access to weather, location, farm details, or scan results if that data appears above.
3. If weather coordinates are missing, say the farmer should set farm location in Settings — do not invent forecasts.
4. Give practical, actionable advice specific to this farmer's crops, soil, irrigation, tasks, and local conditions.
5. Use simple language for a {$experience}-level farmer.
6. Keep chat replies concise (about 2-4 short paragraphs). Prefer clear bullets when listing tasks or weather days.
7. Reference this farmer by name or farm when it feels natural — you are their farm companion, not a generic chatbot.
8. For Nigerian farming, consider local seasons, markets, and practices.
9. Never invent pesticide dosages or medical/legal advice. If unsure about non-weather facts, say so honestly.
10. Prefer decisions tied to today's tasks, field health, recent crop scans, and the 7-day forecast when relevant.
11. When the farmer asks about a scan, reference the latest matching scan findings. Kindwise crop.health scans marked research-backed / auto_verified can be treated as completed results — do not tell the farmer to wait for expert review. Only call a result provisional if verification is needs_retake or disputed.
12. Never reveal chain-of-thought, hidden analysis, or "thinking" steps. Reply with clear farmer-facing advice only.
13. RESPONSE DEPTH ({$depth}): {$depthInstruction}
14. RISK STYLE ({$risk}): {$riskInstruction}
PROMPT;

        $langName = TranslationService::languageName($lang);
        $prompt .= "\n\nLANGUAGE (mandatory): Respond entirely in {$langName}. The farmer's current app language is {$lang}. Ignore the language of earlier chat history if it differs — always answer this turn in {$langName}. Keep language natural, warm, and farmer-friendly. Product/scientific names may stay in English when needed.";

        return $prompt;
    }

    private function buildWeatherContext(User $user): string
    {
        if (! $user->hasFarmCoordinates()) {
            return "WEATHER & SOIL:\n- No farm GPS coordinates saved. Ask the farmer to set farm location in Settings so you can use live weather.\n";
        }

        try {
            $weather = $this->weatherService->getWeatherForUser($user) ?? [];
            $current = $weather['current'] ?? [];
            $temp = $current['temperature'] ?? 'n/a';
            $humidity = $current['humidity'] ?? 'n/a';
            $condition = $current['condition'] ?? 'n/a';
            $wind = $current['windSpeed'] ?? 'n/a';
            $precipNow = $current['precipitation'] ?? 0;

            $lines = [
                'WEATHER & SOIL (live Open-Meteo data for this farm — treat as ground truth):',
                "- Right now: {$temp}°C, {$condition}, humidity {$humidity}%, wind {$wind} km/h, precip {$precipNow} mm.",
            ];

            foreach ($weather['soilHealth'] ?? [] as $item) {
                $label = $item['label'] ?? 'Soil';
                $value = $item['value'] ?? 'n/a';
                $unit = $item['unit'] ?? '';
                $lines[] = "- {$label}: {$value}{$unit}";
            }

            $lines[] = '- 7-day forecast:';
            foreach (array_slice($weather['forecast'] ?? [], 0, 7) as $day) {
                $date = $day['date'] ?? '';
                $dayName = $day['day'] ?? '';
                $cond = $day['condition'] ?? 'n/a';
                $high = $day['high'] ?? $day['max'] ?? 'n/a';
                $low = $day['low'] ?? $day['min'] ?? 'n/a';
                $rainMm = $day['precipitation'] ?? 0;
                $rainChance = $day['precipitationProbability'] ?? 0;
                $lines[] = "  • {$dayName} {$date}: {$cond}, high {$high}° / low {$low}°, rain {$rainMm} mm (~{$rainChance}% chance)";
            }

            $rainyDays = collect($weather['forecast'] ?? [])
                ->filter(fn ($d) => ($d['precipitation'] ?? 0) > 0.5 || ($d['precipitationProbability'] ?? 0) >= 40)
                ->map(fn ($d) => ($d['day'] ?? '').' '.($d['date'] ?? ''))
                ->values()
                ->all();

            if ($rainyDays) {
                $lines[] = '- Likely wetter days this week: '.implode(', ', $rainyDays);
            } else {
                $lines[] = '- No meaningful rain expected in the 7-day forecast.';
            }

            return implode("\n", $lines)."\n";
        } catch (\Throwable $e) {
            Log::warning('Weather fetch failed for AI context: '.$e->getMessage());

            return "WEATHER & SOIL:\n- Weather service temporarily unavailable.\n";
        }
    }

    private function buildFieldsContext(User $user): string
    {
        $fields = FarmField::where('user_id', $user->id)->orderBy('name')->get();
        if ($fields->isEmpty()) {
            return "FARM FIELDS:\n- No fields saved yet.\n";
        }

        $lines = ['FARM FIELDS:'];
        foreach ($fields as $field) {
            $planted = $field->planted_at?->toDateString() ?? 'unknown';
            $harvest = ($field->harvest_start_date && $field->harvest_end_date)
                ? $field->harvest_start_date->toDateString().' to '.$field->harvest_end_date->toDateString()
                : 'not estimated yet';
            $harvested = $field->harvested_at?->toDateString();
            $cycle = $harvested
                ? sprintf(
                    'SUCCESSFULLY_HARVESTED on %s (last crop=%s%s)%s',
                    $harvested,
                    $field->crop ?? 'n/a',
                    $field->yield_note ? ', yield_note='.$field->yield_note : '',
                    ($field->planned_next_crop && $field->planned_plant_at)
                        ? sprintf('; plans next crop=%s on %s', $field->planned_next_crop, $field->planned_plant_at->toDateString())
                        : '; no next crop planned yet',
                )
                : sprintf('growing; harvest_window=%s', $harvest);
            $lines[] = sprintf(
                '- %s: crop=%s, area=%s m2, health=%s%%, moisture=%s%%, status=%s, planted_at=%s, cycle=%s',
                $field->name ?? 'Field',
                $field->crop ?? 'n/a',
                $field->area_m2 ?? 'n/a',
                $field->health_percentage ?? 'n/a',
                $field->moisture_percentage ?? 'n/a',
                $field->status ?? 'active',
                $planted,
                $cycle,
            );
        }

        return implode("\n", $lines)."\n";
    }

    private function buildTasksContext(User $user): string
    {
        $today = now()->toDateString();
        $weekEnd = now()->addDays(7)->toDateString();

        $tasks = CalendarTask::where('user_id', $user->id)
            ->whereBetween('scheduled_date', [$today, $weekEnd])
            ->orderBy('scheduled_date')
            ->limit(20)
            ->get();

        if ($tasks->isEmpty()) {
            return "TASKS (today through next 7 days):\n- No scheduled tasks.\n";
        }

        $lines = ['TASKS (today through next 7 days):'];
        foreach ($tasks as $task) {
            $done = $task->completed ? 'done' : 'pending';
            $date = optional($task->scheduled_date)->toDateString() ?? (string) $task->scheduled_date;
            $lines[] = sprintf(
                '- %s | %s (%s, %s min, impact=%s) [%s]%s',
                $date,
                $task->title,
                $task->period ?? 'anytime',
                $task->duration_minutes ?? 30,
                $task->impact ?? 'medium',
                $done,
                $task->description ? ' — '.$task->description : '',
            );
        }

        return implode("\n", $lines)."\n";
    }

    private function buildJournalContext(User $user): string
    {
        $entries = JournalEntry::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        if ($entries->isEmpty()) {
            return "RECENT FIELD JOURNAL:\n- No recent notes.\n";
        }

        $lines = ['RECENT FIELD JOURNAL:'];
        foreach ($entries as $entry) {
            $date = optional($entry->created_at)?->toDateString() ?? 'n/a';
            $lines[] = sprintf('- %s [%s]: %s', $date, $entry->type ?? 'note', $entry->note ?? '');
        }

        return implode("\n", $lines)."\n";
    }

    private function buildRecentScansContext(User $user): string
    {
        $scans = FarmImageAnalysis::where('user_id', $user->id)
            ->with('farmField:id,name,crop')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        if ($scans->isEmpty()) {
            return "RECENT CROP SCANS:\n- No crop scans yet.\n";
        }

        $lines = ['RECENT CROP SCANS (Kindwise research-backed / auto_verified results are completed; only needs_retake or disputed are provisional):'];
        foreach ($scans as $scan) {
            $analysis = is_array($scan->result_json) ? $scan->result_json : [];
            $date = optional($scan->created_at)?->toDateString() ?? 'n/a';
            $field = $scan->farmField?->name ?? 'General farm';
            $crop = $scan->farmField?->crop ?? 'crop';
            $condition = $analysis['conditionLabel'] ?? ($scan->condition ?? 'unknown');
            $summary = $analysis['summary'] ?? 'No summary';
            $disease = $scan->disease_name ?: ($analysis['disease']['name'] ?? 'none detected');
            $immediate = [];
            if (! empty($analysis['recommendations']['immediate']) && is_array($analysis['recommendations']['immediate'])) {
                $immediate = array_slice($analysis['recommendations']['immediate'], 0, 2);
            }
            $immediateText = $immediate ? implode('; ', $immediate) : 'n/a';
            $verification = $scan->verification_state ?? 'legacy_ineligible';
            $certainty = in_array($verification, ['auto_verified', 'expert_verified'], true)
                || ! empty($analysis['researchBacked'])
                ? 'completed'
                : (in_array($verification, ['needs_retake', 'disputed'], true) ? 'PROVISIONAL' : 'completed');

            $lines[] = sprintf(
                '- Scan #%s on %s | status=%s (%s) | field=%s (%s) | condition=%s | disease=%s | summary=%s | immediate=%s',
                $scan->id,
                $date,
                $certainty,
                $verification,
                $field,
                $crop,
                $condition,
                $disease,
                $summary,
                $immediateText,
            );
        }

        return implode("\n", $lines)."\n";
    }

    private function getRecentConversation(User $user, int $limit = 10): Collection
    {
        return AdvisorConversation::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options
     */
    private function askLlm(array $messages, array $options = []): string
    {
        try {
            return $this->llm->chat($messages, array_merge([
                'timeout' => 45,
                'max_tokens' => 1024,
                'temperature' => 0.5,
            ], $options));
        } catch (\Throwable $e) {
            Log::error('AI advisor request failed', ['error' => $e->getMessage()]);

            return 'I\'m temporarily unavailable. Please try again shortly.';
        }
    }
}
