<?php

namespace App\Services;

use App\Models\AdvisorConversation;
use App\Models\CalendarTask;
use App\Models\FarmField;
use App\Models\FarmImageAnalysis;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiAdvisorService
{
    private string $apiKey;
    private string $model;
    private string $endpoint;
    private string $apiVersion;

    public function __construct(private WeatherService $weatherService)
    {
        $this->apiKey = trim(config('services.github_models.api_key', ''));
        $this->model = trim(config('services.github_models.model', 'openai/gpt-4o-mini'));
        $this->endpoint = trim(config('services.github_models.endpoint', 'https://models.github.ai/inference/chat/completions'));
        $this->apiVersion = trim(config('services.github_models.api_version', '2022-11-28'));
    }

    /**
     * Chat with the AI advisor, passing full user context.
     */
    public function chat(User $user, string $message): string
    {
        AdvisorConversation::create([
            'user_id' => $user->id,
            'role' => 'user',
            'message' => $message,
        ]);

        $lang = $user->preferred_language ?? 'en';
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

        $reply = $this->callGithubModels($messages);

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
     * Generate daily insight for a user (cached per user per day).
     */
    public function dailyInsight(User $user): array
    {
        $cacheKey = "daily_insight_{$user->id}_".date('Y-m-d');

        return Cache::remember($cacheKey, 86400, function () use ($user) {
            return $this->generateDailyInsight($user);
        });
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
     * Estimate market prices for given crops using AI.
     */
    public function estimateMarketPrices(User $user, array $crops): array
    {
        $cacheKey = "market_prices_{$user->id}_".date('Y-m-d');

        return Cache::remember($cacheKey, 86400, function () use ($user, $crops) {
            $cropsStr = implode(', ', $crops);
            $location = $user->farm_location ?? 'Nigeria';

            $prompt = "You are a Nigerian agricultural market analyst. Give me current estimated market prices for these crops in Nigeria: {$cropsStr}. The farmer is located in {$location}. For each crop, provide: commodity name, estimated price per ton in Nigerian Naira (NGN), the nearest major market, and whether the price trend is 'up', 'down', or 'stable' compared to last month. Also provide 2 brief market highlights. Return ONLY valid JSON in this exact format, no markdown, no explanation: {\"prices\": [{\"commodity\": \"name\", \"pricePerTon\": 150000, \"location\": \"market name\", \"trend\": \"up\"}], \"highlights\": [\"highlight 1\", \"highlight 2\"]}";

            $messages = [
                ['role' => 'system', 'content' => 'You are a Nigerian agricultural market analyst. Always respond with valid JSON only, no markdown formatting.'],
                ['role' => 'user', 'content' => $prompt],
            ];

            $reply = $this->callGithubModels($messages);

            $cleaned = preg_replace('/```json\s*|\s*```/', '', $reply);
            $cleaned = trim($cleaned);

            $parsed = json_decode($cleaned, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($parsed['prices'])) {
                return $parsed;
            }

            return $this->fallbackMarketPrices($crops);
        });
    }

    private function generateDailyInsight(User $user): array
    {
        $systemPrompt = $this->buildSystemPrompt($user);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => 'Give me 2 short, actionable farming insights for today based on my farm conditions and current weather. Each insight should have a title (max 8 words) and a description (max 30 words). Return ONLY valid JSON array: [{"title": "...", "description": "..."}]'],
        ];

        $reply = $this->callGithubModels($messages);

        $cleaned = preg_replace('/```json\s*|\s*```/', '', $reply);
        $cleaned = trim($cleaned);

        $parsed = json_decode($cleaned, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
            $insights = [];
            foreach (array_slice($parsed, 0, 3) as $i => $item) {
                $insights[] = [
                    'id' => 'tip-'.($i + 1),
                    'title' => $item['title'] ?? 'Farm tip',
                    'description' => $item['description'] ?? 'Check your crops and field conditions today.',
                ];
            }

            return $insights;
        }

        return [
            ['id' => 'tip-1', 'title' => 'Monitor soil moisture', 'description' => 'Check moisture levels in the morning for optimal irrigation timing.'],
            ['id' => 'tip-2', 'title' => 'Inspect for pests', 'description' => 'Early morning inspection helps catch pest infestations before they spread.'],
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
11. When the farmer asks about a scan, reference the latest matching scan findings (condition, disease, recommendations) and expand with prevention/treatment steps.
PROMPT;

        if ($lang !== 'en') {
            $langName = TranslationService::languageName($lang);
            $prompt .= "\n\nLANGUAGE: The farmer prefers {$langName}. They may write in {$langName} or English. ALWAYS respond in {$langName}. Keep language natural and farmer-friendly.";
        }

        return $prompt;
    }

    private function buildWeatherContext(User $user): string
    {
        if (! $user->farm_latitude || ! $user->farm_longitude) {
            return "WEATHER & SOIL:\n- No farm GPS coordinates saved. Ask the farmer to set farm location in Settings so you can use live weather.\n";
        }

        try {
            $weather = $this->weatherService->getWeather(
                (float) $user->farm_latitude,
                (float) $user->farm_longitude,
            );
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
            $lines[] = sprintf(
                '- %s: crop=%s, area=%s m2, health=%s%%, moisture=%s%%, status=%s',
                $field->name ?? 'Field',
                $field->crop ?? 'n/a',
                $field->area_m2 ?? 'n/a',
                $field->health_percentage ?? 'n/a',
                $field->moisture_percentage ?? 'n/a',
                $field->status ?? 'active',
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

        $lines = ['RECENT CROP SCANS (from the in-app AI crop scanner — treat as ground truth for follow-up questions):'];
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

            $lines[] = sprintf(
                '- Scan #%s on %s | field=%s (%s) | condition=%s | disease=%s | summary=%s | immediate=%s',
                $scan->id,
                $date,
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

    private function getRecentConversation(User $user, int $limit = 10): \Illuminate\Support\Collection
    {
        return AdvisorConversation::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    private function callGithubModels(array $messages): string
    {
        if (empty($this->apiKey)) {
            Log::error('GitHub Models: API key missing. Set GITHUB_MODELS_API_KEY in .env');

            return 'AI advisor is not configured. Please contact support.';
        }

        try {
            Log::info('GitHub Models: sending chat request', ['model' => $this->model, 'message_count' => count($messages)]);

            $response = Http::timeout(45)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Accept' => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => $this->apiVersion,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->endpoint, [
                    'model' => $this->model,
                    'messages' => $messages,
                    'max_tokens' => 1024,
                    'temperature' => 0.5,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? '';

                Log::info('GitHub Models: chat response received', ['model' => $this->model]);

                return trim($content);
            }

            Log::error('GitHub Models API error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'model' => $this->model,
            ]);

            if ($response->status() === 401) {
                return 'AI advisor is not set up correctly. Please contact support.';
            }
            if ($response->status() === 429) {
                return 'AI is busy right now. Please try again in a minute.';
            }

            return 'I apologize, but I\'m having trouble connecting right now. Please try again in a moment.';
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('GitHub Models connection failed', ['message' => $e->getMessage()]);

            return 'Connection to AI service timed out. Please check your internet and try again.';
        } catch (\Exception $e) {
            Log::error('GitHub Models exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 'I\'m temporarily unavailable. Please try again shortly.';
        }
    }

    private function fallbackMarketPrices(array $crops): array
    {
        $prices = [];
        $basePrices = [
            'maize' => 220000, 'rice' => 450000, 'cassava' => 120000,
            'tomatoes' => 350000, 'yam' => 180000, 'beans' => 380000,
            'millet' => 200000, 'sorghum' => 190000, 'groundnut' => 320000,
            'pepper' => 400000, 'onion' => 280000, 'potato' => 250000,
        ];
        $markets = ['Mile 12, Lagos', 'Dawanau, Kano', 'Onitsha Market', 'Bodija, Ibadan', 'Wuse Market, Abuja'];

        foreach ($crops as $crop) {
            $lower = strtolower(trim($crop));
            $price = $basePrices[$lower] ?? 200000;
            $prices[] = [
                'commodity' => ucfirst($crop),
                'pricePerTon' => $price,
                'location' => $markets[array_rand($markets)],
                'trend' => ['up', 'down', 'stable'][array_rand(['up', 'down', 'stable'])],
            ];
        }

        return [
            'prices' => $prices,
            'highlights' => [
                'Market prices are estimated based on recent trends.',
                'Visit local markets for the most accurate pricing.',
            ],
        ];
    }
}
