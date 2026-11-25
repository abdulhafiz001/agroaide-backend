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
    private const UNAVAILABLE_FALLBACK_TEXT = 'I\'m temporarily unavailable. Please try again shortly.';

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

        // Deterministic pre-check so obvious general-chatbot / non-farm questions
        // are always refused and redirected back to the user's farm.
        if ($this->isOffTopicQuestion($message, $user)) {
            $reply = $this->buildOffTopicRefusal($user, $lang);

            AdvisorConversation::create([
                'user_id' => $user->id,
                'role' => 'assistant',
                'message' => $reply,
            ]);

            return $reply;
        }

        $langName = TranslationService::languageName($lang);
        $farmName = $user->farm_name ?? 'your farm';
        $crops = is_array($user->crops) && $user->crops !== [] ? implode(', ', $user->crops) : 'your crops';
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

        // Hard override after history so older messages cannot keep the previous language
        // and the model strictly refuses off-topic non-farm queries.
        $messages[] = [
            'role' => 'user',
            'content' => "SYSTEM REMINDER: Only answer if my last message is related to my farm (\"{$farmName}\"), my crops ({$crops}), soil, weather, crop scans, farm calendar tasks, or agriculture. If my question is unrelated to farming or my farm, DO NOT answer it—instead politely state in 1-2 short sentences that you are my AgroAide Farm Advisor for \"{$farmName}\" and only answer questions about my farm and crops. Reply entirely in {$langName}. Do not use any other language. Do not show thinking.",
        ];

        $rawReply = $this->askLlm($messages);
        $reply = app(LlmResponseCleaner::class)->clean($rawReply);

        if ($reply === '' || $this->isUnavailablePlaceholder($reply)) {
            $reply = $this->buildContextualFallbackReply($user, $message, $lang);
        }

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
            ->reject(fn (AdvisorConversation $msg) => $msg->role === 'assistant' && $this->isUnavailablePlaceholder($msg->message))
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
0. STRICT FARM-ONLY SCOPE (NON-NEGOTIABLE): You are EXCLUSIVELY the farm advisor for {$name}'s farm ("{$farmName}"). You are NOT a general-purpose chatbot. ONLY answer questions related to {$name}'s farm, their crops ({$crops}), fields, soil, irrigation, local weather forecast, crop scans/health, pests/diseases, farm calendar tasks, market prices, or agricultural practices relevant to their farm. If the user asks about ANYTHING unrelated to farming or their farm (for example: politics, sports, programming/code, math homework, history, geography, celebrities, movies/music, cooking recipes, or general trivia), DO NOT answer the off-topic question—not even briefly. Instead, politely reply in 1-2 short sentences that you are their AgroAide Farm Advisor for "{$farmName}" and can only answer questions about their farm, crops ({$crops}), weather, soil, and farm activities.
1. You already have live farm + weather + crop-scan data above. USE IT. When asked about rain, temperature, soil, tasks, fields, or a recent scan, answer from this context first.
2. Never say you lack access to weather, location, farm details, or scan results if that data appears above.
3. If weather coordinates are missing, say the farmer should set farm location in Settings — do not invent forecasts.
4. Give practical, actionable advice specific to this farmer's crops, soil, irrigation, tasks, and local conditions. Even when the farmer asks a general farming question, anchor your answer to "{$farmName}" in {$location}, their crops ({$crops}), soil ({$soilType}), and live forecast.
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
            ->reject(fn (AdvisorConversation $msg) => $msg->role === 'assistant' && $this->isUnavailablePlaceholder($msg->message))
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
                'timeout' => 25,
                'max_tokens' => 1024,
                'temperature' => 0.5,
            ], $options));
        } catch (\Throwable $e) {
            Log::error('AI advisor request failed', ['error' => $e->getMessage()]);

            return self::UNAVAILABLE_FALLBACK_TEXT;
        }
    }

    private function isUnavailablePlaceholder(string $text): bool
    {
        $normalized = strtolower(trim($text));

        return $normalized === strtolower(self::UNAVAILABLE_FALLBACK_TEXT)
            || str_starts_with($normalized, "i'm temporarily unavailable")
            || str_starts_with($normalized, 'im temporarily unavailable');
    }

    /**
     * Detect clearly non-agricultural / general-chatbot queries so the advisor
     * stays strictly scoped to the user's farm.
     */
    private function isOffTopicQuestion(string $message, User $user): bool
    {
        $lower = strtolower(trim($message));
        if ($lower === '') {
            return false;
        }

        // Dynamic crop/farm terms from the user's profile and fields.
        $userTerms = [];
        if (is_string($user->farm_name) && trim($user->farm_name) !== '') {
            $userTerms[] = strtolower(trim($user->farm_name));
        }
        if (is_array($user->crops)) {
            foreach ($user->crops as $crop) {
                if (is_string($crop) && trim($crop) !== '') {
                    $userTerms[] = strtolower(trim($crop));
                }
            }
        }
        try {
            foreach (FarmField::where('user_id', $user->id)->pluck('crop')->all() as $fieldCrop) {
                if (is_string($fieldCrop) && trim($fieldCrop) !== '') {
                    $userTerms[] = strtolower(trim($fieldCrop));
                }
            }
        } catch (\Throwable) {
            // Ignore field lookup errors during off-topic precheck.
        }

        foreach ($userTerms as $term) {
            if ($term !== '' && str_contains($lower, $term)) {
                return false;
            }
        }

        // Agricultural, weather, soil, pest, and AgroAide domain keywords (English + Hausa/Yoruba/Pidgin common terms).
        $farmPattern = '/\b(farm|farmer|farming|field|fields|plot|plots|crop|crops|plant|plants|planting|seed|seeds|seedling|harvest|harvesting|weed|weeding|weeds|soil|loam|clay|sandy|fertiliz|npk|urea|compost|manure|mulch|irrigat|water|watering|moisture|drainage|weather|rain|raining|rainfall|forecast|temperature|humidity|wind|drought|harmattan|season|dry season|wet season|spray|spraying|pest|pests|disease|diseases|blight|rust|mosaic|rot|wilting|wilt|yellow|brown|spot|spots|leaf|leaves|stem|root|roots|insect|worm|armyworm|aphid|fungus|fungicide|pesticide|herbicide|neem|scan|scans|diagnos|outbreak|task|tasks|calendar|schedule|journal|yield|storage|market|price|prices|agro|agroaide|agronom|maize|corn|cassava|rice|yam|tomato|tomatoes|pepper|cowpea|beans|groundnut|peanut|plantain|banana|sorghum|millet|cocoa|palm|okro|okra|onion|garlic|ginger|potato|vegetable|livestock|poultry|cattle|goat|sheep|fish|acre|hectare|gona|shuka|amfani|ruwa|sama|kasa|masara|rogo|shinkafa|doya|oko|ogbin|agbado|egusi|eroja|ojo)\b/i';

        if (preg_match($farmPattern, $lower) === 1) {
            return false;
        }

        // Obvious non-farm / general-chatbot topics (coding, politics, sports, entertainment, homework, trivia, etc.).
        $offTopicPatterns = [
            '/\b(python|javascript|typescript|java|c\+\+|html|css|php|sql|react|flutter|laravel|coding|programmer|programming|algorithm|leetcode|source code|write (?:a |me )?code|build (?:a |me )?(?:website|app))\b/i',
            '/\b(president|prime minister|governor|senator|election|politician|political party|parliament|constitution|world war|independence day)\b/i',
            '/\b(football|soccer|premier league|champions league|world cup|la liga|arsenal|chelsea|manchester|liverpool|barcelona|real madrid|messi|ronaldo|basketball|nba|tennis|olympics|betting|sportybet|bet9ja)\b/i',
            '/\b(movie|movies|netflix|cinema|actor|actress|celebrity|musician|burna boy|davido|wizkid|taylor swift|drake|grammy|song lyrics)\b/i',
            '/\b(bitcoin|ethereum|cryptocurrency|crypto|forex trading|stock market|nft|blockchain)\b/i',
            '/\b(tell me a joke|write (?:a |me a )?(?:poem|story|essay|song|rap|letter|cv|resume)|homework|assignment|quadratic|calculus|trigonometry|solve this equation)\b/i',
            '/\b(capital of|who invented|who discovered|who is the richest|who is the tallest|planet|solar system|galaxy|dinosaurs)\b/i',
            '/\b(how to cook|recipe for|jollof rice recipe|bake a cake|girlfriend|boyfriend|dating advice|relationship advice|horoscope|zodiac)\b/i',
            '/^\s*what is \d+\s*[\+\-\*\/x]\s*\d+/i',
        ];

        foreach ($offTopicPatterns as $pattern) {
            if (preg_match($pattern, $lower) === 1) {
                return true;
            }
        }

        // Generic encyclopedia / trivia openers that do not mention anything farm-related.
        if (preg_match('/^\s*(who is|who was|what is the capital|where is the country|when was .+ born|explain quantum|define democracy|write an essay)\b/i', $lower) === 1) {
            return true;
        }

        return false;
    }

    private function buildOffTopicRefusal(User $user, string $lang = 'en'): string
    {
        $farmName = is_string($user->farm_name) && trim($user->farm_name) !== ''
            ? trim($user->farm_name)
            : 'your farm';
        $crops = is_array($user->crops) && $user->crops !== []
            ? implode(', ', $user->crops)
            : 'your crops';

        return match ($lang) {
            'ha' => "Ni mai ba da shawara na AgroAide ne don gonar ku ({$farmName}) kawai. Ina amsa tambayoyi ne kawai game da gonar ku, amfanin gona ({$crops}), yanayi, kasa, da ayyukan gona—don Allah ku tambaye ni wani abu game da gonar ku!",
            'yo' => "Olùdámọ̀ràn AgroAide fún oko rẹ ({$farmName}) nìkan ni mí. Èmi kì í dáhùn ibeere tí kò bá ní í ṣe pẹ̀lú oko rẹ, irúgbìn rẹ ({$crops}), ojú-ọjọ́, ilẹ̀, tàbí iṣẹ́ oko rẹ—jọ̀wọ́ béèrè ohun kan nípa oko rẹ!",
            'pcm' => "I be your AgroAide Farm Advisor for {$farmName} only. I dey only answer questions wey concern your farm, your crops ({$crops}), weather, soil, crop scans, and farm work—abeg ask me question about your farm!",
            default => "I'm your AgroAide Farm Advisor for {$farmName}, so I only answer questions related to your farm, your crops ({$crops}), local weather, soil health, crop scans, and farm tasks. Please ask me a question about your farm or fields!",
        };
    }

    /**
     * Build a personalized farm-grounded reply directly from the farmer's live
     * profile, weather, fields, tasks, and scans when external LLM APIs are unreachable.
     */
    private function buildContextualFallbackReply(User $user, string $message, string $lang = 'en'): string
    {
        if ($this->isOffTopicQuestion($message, $user)) {
            return $this->buildOffTopicRefusal($user, $lang);
        }

        $name = $user->name ?? 'Farmer';
        $farmName = $user->farm_name ?? 'your farm';
        $location = $user->farm_location ?? 'Nigeria';
        $crops = is_array($user->crops) && $user->crops !== [] ? implode(', ', $user->crops) : 'your crops';
        $soilType = $user->soil_type ?? 'loamy';
        $irrigation = $user->irrigation_access ?? 'rain-fed';
        $pulse = $this->buildTodayFarmPulse($user);

        $pendingTasks = CalendarTask::where('user_id', $user->id)
            ->where('completed', false)
            ->orderBy('scheduled_date')
            ->limit(3)
            ->get();

        $latestScan = FarmImageAnalysis::where('user_id', $user->id)
            ->where(function ($q) {
                $q->whereNull('processing_state')->orWhere('processing_state', 'completed');
            })
            ->latest()
            ->first();

        $fields = FarmField::where('user_id', $user->id)->limit(4)->get();
        $lower = strtolower($message);

        $weatherSentence = $pulse['rainToday'] || $pulse['rainTonight']
            ? "For {$farmName} in {$location}, rain chance is around {$pulse['todayRainChance']}% today ({$pulse['tonightMaxChance']}% tonight), so hold off on foliar spraying or fertilizer application until after the rain passes and check field drainage."
            : "For {$farmName} in {$location}, rain chance is low today (~{$pulse['todayRainChance']}%), giving you a good dry window for weeding, scouting {$crops}, or early-morning watering.";

        if (preg_match('/\b(scan|disease|blight|rust|mosaic|spot|yellow|wilt|leaf|leaves|pest)\b/i', $lower) === 1 && $latestScan) {
            $res = is_array($latestScan->result_json) ? $latestScan->result_json : [];
            $scanCrop = $res['crop'] ?? $crops;
            $condLabel = $res['conditionLabel'] ?? $latestScan->condition ?? 'assessed';
            $diseaseName = $res['disease']['name'] ?? null;
            $scanSummary = $res['summary'] ?? '';

            $scanDetail = $diseaseName
                ? "Your latest crop scan on {$scanCrop} was marked {$condLabel} ({$diseaseName}). {$scanSummary}"
                : "Your latest crop scan on {$scanCrop} was marked {$condLabel} with no major disease flagged. {$scanSummary}";

            return trim("{$scanDetail}\n\n{$weatherSentence}");
        }

        $taskLine = '';
        if ($pendingTasks->isNotEmpty()) {
            $taskTitles = $pendingTasks
                ->map(fn ($t) => "{$t->title} (due ".(optional($t->scheduled_date)->toDateString() ?? (string) $t->scheduled_date).')')
                ->implode(', ');
            $taskLine = "Your upcoming scheduled farm tasks are: {$taskTitles}.";
        } else {
            $taskLine = "You have no overdue calendar tasks right now, so focus on routine field scouting for your {$crops} plots.";
        }

        $fieldLine = '';
        if ($fields->isNotEmpty()) {
            $fieldSummary = $fields
                ->map(fn ($f) => "{$f->name} ({$f->crop}, status: {$f->status})")
                ->implode('; ');
            $fieldLine = "Active fields on {$farmName}: {$fieldSummary}.";
        } else {
            $fieldLine = "On {$farmName}, you are managing {$crops} on {$soilType} soil ({$irrigation} irrigation).";
        }

        return trim("Hello {$name}! {$weatherSentence}\n\n{$fieldLine} {$taskLine}");
    }
}
