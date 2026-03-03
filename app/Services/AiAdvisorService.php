<?php

namespace App\Services;

use App\Models\AdvisorConversation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiAdvisorService
{
    private string $apiKey;
    private string $model;
    private string $fallbackModel = 'meta-llama/llama-3.2-3b-instruct:free';
    private string $endpoint = 'https://openrouter.ai/api/v1/chat/completions';

    public function __construct(private WeatherService $weatherService)
    {
        $this->apiKey = trim(config('services.openrouter.api_key') ?? env('OPENROUTER_API_KEY', '') ?? '');
        $this->model = trim(config('services.openrouter.model') ?? env('OPENROUTER_MODEL', 'deepseek/deepseek-r1-0528:free') ?? '');
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

        $systemPrompt = $this->buildSystemPrompt($user);
        $conversationHistory = $this->getRecentConversation($user, 10);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($conversationHistory as $msg) {
            $messages[] = [
                'role' => $msg->role,
                'content' => $msg->message,
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        $reply = $this->callOpenRouter($messages);

        AdvisorConversation::create([
            'user_id' => $user->id,
            'role' => 'assistant',
            'message' => $reply,
        ]);

        return $reply;
    }

    /**
     * Generate daily insight for a user (cached per user per day).
     */
    public function dailyInsight(User $user): array
    {
        $cacheKey = "daily_insight_{$user->id}_" . date('Y-m-d');

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
        $cacheKey = "market_prices_{$user->id}_" . date('Y-m-d');

        return Cache::remember($cacheKey, 86400, function () use ($user, $crops) {
            $cropsStr = implode(', ', $crops);
            $location = $user->farm_location ?? 'Nigeria';

            $prompt = "You are a Nigerian agricultural market analyst. Give me current estimated market prices for these crops in Nigeria: {$cropsStr}. The farmer is located in {$location}. For each crop, provide: commodity name, estimated price per ton in Nigerian Naira (NGN), the nearest major market, and whether the price trend is 'up', 'down', or 'stable' compared to last month. Also provide 2 brief market highlights. Return ONLY valid JSON in this exact format, no markdown, no explanation: {\"prices\": [{\"commodity\": \"name\", \"pricePerTon\": 150000, \"location\": \"market name\", \"trend\": \"up\"}], \"highlights\": [\"highlight 1\", \"highlight 2\"]}";

            $messages = [
                ['role' => 'system', 'content' => 'You are a Nigerian agricultural market analyst. Always respond with valid JSON only, no markdown formatting.'],
                ['role' => 'user', 'content' => $prompt],
            ];

            $reply = $this->callOpenRouter($messages);

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

        $reply = $this->callOpenRouter($messages);

        $cleaned = preg_replace('/```json\s*|\s*```/', '', $reply);
        $cleaned = trim($cleaned);

        $parsed = json_decode($cleaned, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
            $insights = [];
            foreach (array_slice($parsed, 0, 3) as $i => $item) {
                $insights[] = [
                    'id' => 'tip-' . ($i + 1),
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

    private function buildSystemPrompt(User $user): string
    {
        $name = $user->name ?? 'Farmer';
        $farmName = $user->farm_name ?? 'the farm';
        $location = $user->farm_location ?? 'Nigeria';
        $crops = is_array($user->crops) ? implode(', ', $user->crops) : 'various crops';
        $soilType = $user->soil_type ?? 'unknown';
        $experience = $user->experience_level ?? 'beginner';
        $irrigation = $user->irrigation_access ?? 'rain-fed';
        $farmSize = $user->farm_size_hectares ?? 0;

        $weatherContext = '';
        if ($user->farm_latitude && $user->farm_longitude) {
            try {
                $weather = $this->weatherService->getWeather(
                    (float) $user->farm_latitude,
                    (float) $user->farm_longitude,
                );
                $current = $weather['current'] ?? [];
                $temp = $current['temperature'] ?? 'unknown';
                $humidity = $current['humidity'] ?? 'unknown';
                $condition = $current['condition'] ?? 'unknown';
                $weatherContext = "Current weather: {$temp}°C, {$humidity}% humidity, {$condition}.";

                $soilData = $weather['soilHealth'] ?? [];
                foreach ($soilData as $item) {
                    if ($item['label'] === 'Soil temp') {
                        $weatherContext .= " Soil temperature: {$item['value']}°C.";
                    }
                    if ($item['label'] === 'Moisture') {
                        $weatherContext .= " Soil moisture: {$item['value']}%.";
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Weather fetch failed for AI context: ' . $e->getMessage());
            }
        }

        return <<<PROMPT
You are AgroAide AI, a personalized agricultural advisor for Nigerian farmers. You are speaking with {$name}, who manages "{$farmName}" in {$location}.

Farm details:
- Size: {$farmSize} hectares
- Crops: {$crops}
- Soil type: {$soilType}
- Irrigation: {$irrigation}
- Experience: {$experience}
{$weatherContext}

Important rules:
1. Always give practical, actionable advice specific to this farmer's context
2. Use simple language appropriate for a {$experience}-level farmer
3. If you're not sure about something, say so honestly — never make up data
4. Reference the farmer's specific crops, location, and conditions when relevant
5. Keep responses concise but informative (2-4 paragraphs max for chat)
6. For Nigerian farming context, consider local seasons, markets, and practices
7. Never provide medical or legal advice
8. Always be encouraging and supportive
PROMPT;
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

    private function callOpenRouter(array $messages): string
    {
        if (empty($this->apiKey)) {
            Log::error('OpenRouter: API key missing. Set OPENROUTER_API_KEY in .env');
            return 'AI advisor is not configured. Please contact support.';
        }

        try {
            $response = Http::timeout(45)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'HTTP-Referer' => config('app.url', 'https://agroaide.ng'),
                    'X-Title' => 'AgroAide Platform',
                    'Content-Type' => 'application/json',
                ])
                ->post($this->endpoint, [
                    'model' => $this->model,
                    'messages' => $messages,
                    'max_tokens' => 1024,
                    'temperature' => 0.7,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? '';

                $content = preg_replace('/<think>.*?<\/think>/s', '', $content);
                return trim($content);
            }

            $body = $response->json();
            $errorMsg = $body['error']['message'] ?? $body['error']['code'] ?? $response->body();
            Log::error('OpenRouter API error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'model' => $this->model,
            ]);

            if ($response->status() === 401) {
                return 'OpenRouter API key is invalid or expired. Go to openrouter.ai/keys to create a new key, then update OPENROUTER_API_KEY in your backend .env file.';
            }
            if ($response->status() === 429) {
                return 'AI is busy right now. Please try again in a minute.';
            }

            // Try fallback model on 4xx/5xx (e.g. model deprecated or overloaded)
            if ($this->model !== $this->fallbackModel) {
                Log::info('OpenRouter: Retrying with fallback model', ['fallback' => $this->fallbackModel]);
                $originalModel = $this->model;
                $this->model = $this->fallbackModel;
                $result = $this->callOpenRouter($messages);
                $this->model = $originalModel;
                return $result;
            }

            return 'I apologize, but I\'m having trouble connecting right now. Please try again in a moment.';
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('OpenRouter connection failed', ['message' => $e->getMessage()]);
            return 'Connection to AI service timed out. Please check your internet and try again.';
        } catch (\Exception $e) {
            Log::error('OpenRouter exception', [
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
