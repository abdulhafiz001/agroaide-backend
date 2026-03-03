<?php

namespace App\Services;

use App\Models\FarmField;
use App\Models\FarmImageAnalysis;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FarmImageAnalysisService
{
    private string $openRouterKey;
    private string $visionModel;
    private string $openRouterEndpoint = 'https://openrouter.ai/api/v1/chat/completions';
    private string $plantNetKey;
    private string $plantNetEndpoint;

    public function __construct(private WeatherService $weatherService)
    {
        $this->openRouterKey = trim(config('services.openrouter.api_key') ?? env('OPENROUTER_API_KEY', ''));
        $this->visionModel = trim(config('services.openrouter.vision_model') ?? env('OPENROUTER_VISION_MODEL', 'qwen/qwen3-vl-235b-a22b-thinking'));
        $this->plantNetKey = trim(config('services.plantnet.api_key') ?? env('PLANTNET_API_KEY', ''));
        $this->plantNetEndpoint = trim(config('services.plantnet.endpoint') ?? 'https://my-api.plantnet.org/v2');
    }

    /**
     * Run full analysis pipeline: PlantNet disease check → OpenRouter vision analysis.
     */
    public function analyze(User $user, string $base64Image, ?int $farmFieldId = null): array
    {
        $field = null;
        if ($farmFieldId) {
            $field = FarmField::where('user_id', $user->id)->where('id', $farmFieldId)->first();
        }

        $plantNetResult = $this->callPlantNet($base64Image);
        $visionResult = $this->callVisionModel($user, $field, $base64Image, $plantNetResult);

        $storedPath = $this->storeImage($user, $base64Image);

        FarmImageAnalysis::create([
            'user_id' => $user->id,
            'farm_field_id' => $farmFieldId,
            'image_path' => $storedPath,
            'condition' => $visionResult['condition'] ?? 'unknown',
            'result_json' => $visionResult,
        ]);

        if ($field && isset($visionResult['condition'])) {
            $this->updateFieldHealth($field, $visionResult['condition']);
        }

        return $visionResult;
    }

    /**
     * Get scan history for a user.
     */
    public function getHistory(User $user, int $limit = 10): array
    {
        return FarmImageAnalysis::where('user_id', $user->id)
            ->with('farmField:id,name,crop')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn (FarmImageAnalysis $a) => [
                'id' => (string) $a->id,
                'date' => $a->created_at->toIso8601String(),
                'condition' => $a->condition,
                'fieldName' => $a->farmField?->name,
                'fieldCrop' => $a->farmField?->crop,
                'summary' => $a->result_json['summary'] ?? null,
                'imagePath' => $a->image_path ? Storage::url($a->image_path) : null,
            ])
            ->toArray();
    }

    private function callPlantNet(string $base64Image): ?array
    {
        if (empty($this->plantNetKey)) {
            return null;
        }

        try {
            $imageData = $this->extractRawBase64($base64Image);
            $tempPath = tempnam(sys_get_temp_dir(), 'plantnet_');
            file_put_contents($tempPath, base64_decode($imageData));

            $response = Http::timeout(20)
                ->attach('images', file_get_contents($tempPath), 'scan.jpg')
                ->post("{$this->plantNetEndpoint}/identify/all?include-related-images=false&no-reject=false&nb-results=5&lang=en&type=kt&api-key={$this->plantNetKey}");

            @unlink($tempPath);

            if ($response->successful()) {
                $data = $response->json();
                $results = $data['results'] ?? [];

                if (empty($results)) {
                    return ['identified' => false, 'raw' => $data];
                }

                $topResults = array_slice($results, 0, 3);
                $mapped = array_map(fn ($r) => [
                    'scientificName' => $r['species']['scientificNameWithoutAuthor'] ?? 'Unknown',
                    'commonNames' => $r['species']['commonNames'] ?? [],
                    'score' => round(($r['score'] ?? 0) * 100, 1),
                    'family' => $r['species']['family']['scientificNameWithoutAuthor'] ?? null,
                    'genus' => $r['species']['genus']['scientificNameWithoutAuthor'] ?? null,
                ], $topResults);

                return [
                    'identified' => true,
                    'plantIdentifications' => $mapped,
                    'bestMatch' => $mapped[0] ?? null,
                ];
            }

            Log::warning('PlantNet API returned non-success', ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        } catch (\Exception $e) {
            Log::warning('PlantNet API call failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function callVisionModel(User $user, ?FarmField $field, string $base64Image, ?array $plantNetResult): array
    {
        if (empty($this->openRouterKey)) {
            return $this->fallbackResult('AI service is not configured.');
        }

        $systemPrompt = $this->buildAnalysisPrompt($user, $field, $plantNetResult);

        $imageUrl = $base64Image;
        if (! str_starts_with($imageUrl, 'data:image/')) {
            $imageUrl = "data:image/jpeg;base64,{$imageUrl}";
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Analyze this farm/crop image. Determine the condition and provide a detailed diagnosis. Return ONLY valid JSON matching the required format. No markdown, no explanation outside the JSON.',
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => ['url' => $imageUrl],
                    ],
                ],
            ],
        ];

        try {
            $response = Http::timeout(90)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->openRouterKey,
                    'HTTP-Referer' => config('app.url', 'https://agroaide.ng'),
                    'X-Title' => 'AgroAide Farm Scanner',
                    'Content-Type' => 'application/json',
                ])
                ->post($this->openRouterEndpoint, [
                    'model' => $this->visionModel,
                    'messages' => $messages,
                    'max_tokens' => 2048,
                    'temperature' => 0.3,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? '';

                $content = preg_replace('/<think>.*?<\/think>/s', '', $content);
                $content = trim($content);

                $cleaned = preg_replace('/```json\s*|\s*```/', '', $content);
                $cleaned = trim($cleaned);

                $parsed = json_decode($cleaned, true);

                if (json_last_error() === JSON_ERROR_NONE && isset($parsed['condition'])) {
                    $parsed['condition'] = $this->normalizeCondition($parsed['condition']);
                    if ($plantNetResult && ($plantNetResult['identified'] ?? false)) {
                        $parsed['plantIdentification'] = $plantNetResult['bestMatch'] ?? null;
                    }
                    return $parsed;
                }

                Log::warning('Vision model returned non-JSON', ['raw' => substr($content, 0, 500)]);
                return $this->parseUnstructuredResponse($content, $plantNetResult);
            }

            Log::error('OpenRouter vision API error', ['status' => $response->status(), 'body' => $response->body()]);
            return $this->fallbackResult('AI analysis service is temporarily unavailable. Please try again.');
        } catch (\Exception $e) {
            Log::error('Vision analysis exception', ['error' => $e->getMessage()]);
            return $this->fallbackResult('Analysis timed out. The image may be too large, or the service is busy. Please try again.');
        }
    }

    private function buildAnalysisPrompt(User $user, ?FarmField $field, ?array $plantNetResult): string
    {
        $name = $user->name ?? 'Farmer';
        $farmName = $user->farm_name ?? 'the farm';
        $location = $user->farm_location ?? 'Nigeria';
        $crops = is_array($user->crops) ? implode(', ', $user->crops) : 'various crops';
        $soilType = $user->soil_type ?? 'unknown';
        $experience = $user->experience_level ?? 'beginner';
        $irrigation = $user->irrigation_access ?? 'rain-fed';
        $farmSize = $user->farm_size_hectares ?? 0;

        $fieldContext = '';
        if ($field) {
            $daysSince = $field->days_since_planting;
            $daysStr = $daysSince !== null ? "{$daysSince} days since planting" : 'planting date unknown';
            $fieldContext = <<<FIELD

Specific field being scanned:
- Field name: {$field->name}
- Crop: {$field->crop}
- Area: {$field->area_hectares} hectares
- Current recorded health: {$field->health_percentage}%
- Soil moisture: {$field->moisture_percentage}%
- Growth stage: {$daysStr}
- Status: {$field->status}
FIELD;
        }

        $plantNetContext = '';
        if ($plantNetResult && ($plantNetResult['identified'] ?? false)) {
            $best = $plantNetResult['bestMatch'] ?? [];
            $sciName = $best['scientificName'] ?? 'Unknown';
            $commonNames = implode(', ', $best['commonNames'] ?? []);
            $score = $best['score'] ?? 0;
            $plantNetContext = <<<PLANTNET

PlantNet plant identification results (use this to cross-reference your visual analysis):
- Best match: {$sciName} ({$commonNames}) with {$score}% confidence
PLANTNET;
        }

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
                $weatherContext = "\nCurrent weather at farm: {$temp}°C, {$humidity}% humidity, {$condition}.";
            } catch (\Exception $e) {
                Log::debug('Weather unavailable for scan context');
            }
        }

        return <<<PROMPT
You are AgroAide Crop Scanner, an expert agricultural diagnostic AI for Nigerian farmers. You are analyzing a farm image for {$name}, who manages "{$farmName}" in {$location}.

Farm context:
- Size: {$farmSize} hectares
- Main crops: {$crops}
- Soil type: {$soilType}
- Irrigation: {$irrigation}
- Farmer experience: {$experience}{$fieldContext}{$plantNetContext}{$weatherContext}

YOUR TASK: Analyze the uploaded image and diagnose the condition of the crops/farm visible.

You MUST return ONLY valid JSON in this exact format (no markdown, no explanation outside JSON):
{
  "condition": "healthy|good|fair|poor|diseased|critical",
  "conditionLabel": "Human-readable label like 'Healthy', 'Diseased - Early Blight', etc.",
  "confidencePercent": 85,
  "summary": "2-3 sentence summary of what you see in the image and overall assessment.",
  "details": {
    "plantsVisible": "What plants/crops you can identify in the image",
    "growthStage": "Estimated growth stage (seedling, vegetative, flowering, fruiting, mature)",
    "overallVigor": "Assessment of plant vigor and growth"
  },
  "disease": null or {
    "name": "Disease name",
    "scientificName": "If known",
    "symptoms": ["symptom 1", "symptom 2"],
    "cause": "What causes this disease",
    "severity": "mild|moderate|severe",
    "spreadRisk": "low|medium|high"
  },
  "recommendations": {
    "immediate": ["Action 1 the farmer should take now", "Action 2"],
    "products": [
      {"name": "Product name", "type": "fungicide|pesticide|fertilizer|other", "usage": "How to apply"}
    ],
    "prevention": ["Prevention tip 1", "Prevention tip 2"],
    "longTerm": ["Long-term advice 1"]
  },
  "personalizedNote": "A warm, encouraging note specific to this farmer's context, location, experience level, and current conditions. Use simple language for beginners."
}

IMPORTANT RULES:
1. Be accurate — if you cannot clearly identify a disease, say the crops look healthy or describe what you see honestly.
2. For Nigerian context: recommend locally available products and practices.
3. Adjust language complexity to the farmer's experience level ({$experience}).
4. If the image is not of crops/farm, still respond with the JSON format but set condition to "unknown" and explain in the summary.
5. The "disease" field should be null if no disease is detected.
6. Always provide at least 2 recommendations even for healthy crops.
PROMPT;
    }

    private function normalizeCondition(string $condition): string
    {
        $condition = strtolower(trim($condition));
        $map = [
            'healthy' => 'healthy',
            'good' => 'good',
            'great' => 'good',
            'okay' => 'fair',
            'fair' => 'fair',
            'moderate' => 'fair',
            'poor' => 'poor',
            'bad' => 'poor',
            'diseased' => 'diseased',
            'infected' => 'diseased',
            'critical' => 'critical',
            'severe' => 'critical',
            'unknown' => 'unknown',
        ];

        return $map[$condition] ?? 'unknown';
    }

    private function parseUnstructuredResponse(string $rawText, ?array $plantNetResult): array
    {
        $result = [
            'condition' => 'unknown',
            'conditionLabel' => 'Analysis Complete',
            'confidencePercent' => 50,
            'summary' => $rawText ?: 'The AI could not provide a structured analysis. Please try again with a clearer image.',
            'details' => null,
            'disease' => null,
            'recommendations' => [
                'immediate' => ['Take a clearer photo of the affected area', 'Consult a local agricultural extension officer'],
                'products' => [],
                'prevention' => ['Regular crop monitoring helps catch problems early'],
                'longTerm' => [],
            ],
            'personalizedNote' => 'We could not fully analyze your image this time. Try taking a closer photo of the leaves or affected area for better results.',
        ];

        if ($plantNetResult && ($plantNetResult['identified'] ?? false)) {
            $result['plantIdentification'] = $plantNetResult['bestMatch'] ?? null;
        }

        return $result;
    }

    private function fallbackResult(string $message): array
    {
        return [
            'condition' => 'unknown',
            'conditionLabel' => 'Scan Unavailable',
            'confidencePercent' => 0,
            'summary' => $message,
            'details' => null,
            'disease' => null,
            'recommendations' => [
                'immediate' => ['Please try scanning again in a few minutes'],
                'products' => [],
                'prevention' => [],
                'longTerm' => [],
            ],
            'personalizedNote' => $message,
        ];
    }

    private function storeImage(User $user, string $base64Image): ?string
    {
        try {
            $imageData = $this->extractRawBase64($base64Image);
            $decoded = base64_decode($imageData);
            if (! $decoded) {
                return null;
            }

            $dir = "farm-scans/{$user->id}";
            $filename = date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 8) . '.jpg';
            $path = "{$dir}/{$filename}";

            Storage::disk('local')->put($path, $decoded);

            return $path;
        } catch (\Exception $e) {
            Log::warning('Failed to store scan image', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function extractRawBase64(string $input): string
    {
        if (str_contains($input, ',')) {
            return explode(',', $input, 2)[1];
        }
        return $input;
    }

    private function updateFieldHealth(FarmField $field, string $condition): void
    {
        $healthMap = [
            'healthy' => 95,
            'good' => 85,
            'fair' => 65,
            'poor' => 40,
            'diseased' => 30,
            'critical' => 15,
        ];

        $newHealth = $healthMap[$condition] ?? null;
        if ($newHealth !== null) {
            $field->update(['health_percentage' => $newHealth]);
        }
    }
}
