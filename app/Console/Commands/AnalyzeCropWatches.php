<?php

namespace App\Console\Commands;

use App\Models\CropWatch;
use App\Services\NotificationDispatcher;
use App\Services\SeasonalCalendarService;
use App\Services\TranslationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnalyzeCropWatches extends Command
{
    protected $signature = 'agroaide:analyze-crop-watches';

    protected $description = 'Every ~6h: analyze NEW / waiting crop watches only (no repeat notifies)';

    public function __construct(
        private SeasonalCalendarService $seasonalCalendar,
        private NotificationDispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Only new watches (never analyzed) OR watches still waiting for a planting window.
        // Already-notified outcomes (window_open / season_passed) are skipped to avoid repetition.
        $watches = CropWatch::query()
            ->where('notify_when_planting_window', true)
            ->where(function ($q) {
                $q->whereNull('last_analyzed_at')
                    ->orWhere('last_analysis_status', 'waiting')
                    ->orWhereNull('last_analysis_status');
            })
            ->where(function ($q) {
                $q->whereNull('status')->orWhereIn('status', ['active', 'waiting']);
            })
            ->with('user')
            ->get();

        $this->info('Analyzing '.$watches->count().' new/waiting crop watch(es).');
        $sent = 0;

        foreach ($watches as $watch) {
            $user = $watch->user;
            if (! $user) {
                continue;
            }

            // Hard stop: already notified for a terminal/open outcome — never spam again.
            if ($watch->last_notified_on
                && in_array($watch->last_analysis_status, ['window_open', 'season_passed', 'invalid'], true)) {
                continue;
            }

            $lat = $user->farm_latitude !== null ? (float) $user->farm_latitude : null;
            $lng = $user->farm_longitude !== null ? (float) $user->farm_longitude : null;
            $zone = $this->seasonalCalendar->resolveZone($lat, $lng, $user->farm_location);
            $placeLabel = $this->seasonalCalendar->locationPhrase($user, $zone);

            // Normalize aliases early (Beans → Cowpea) so known crops never hit the AI invalid path.
            $cropKey = $this->seasonalCalendar->normalizeCropName($watch->crop);
            if ($cropKey !== $watch->crop) {
                $watch->crop = $cropKey;
                $watch->save();
            }

            if (! $this->seasonalCalendar->isKnownCrop($cropKey)
                && ! $this->looksLikeValidCropViaAi($watch->crop)) {
                $title = "Unknown crop: {$watch->crop}";
                $message = $this->aiMessage($user, 'invalid', $watch->crop, $placeLabel, null);
                $notification = $this->dispatcher->notify(
                    $user,
                    'crop_watch_invalid',
                    $title,
                    $message,
                    [
                        'crop' => $watch->crop,
                        'watchId' => $watch->id,
                        'analysis' => 'invalid',
                        'location' => $placeLabel,
                    ],
                    [
                        'push' => true,
                        'preference' => 'plantingWindowAlerts',
                        'dedupeMinutes' => 10080,
                        'dedupeKey' => 'watchId',
                    ],
                );
                if ($notification) {
                    $sent++;
                }
                $watch->delete();

                continue;
            }

            if ($this->seasonalCalendar->seasonPassedThisYear($cropKey, $zone)) {
                if ($watch->last_analysis_status === 'season_passed') {
                    $watch->update(['last_analyzed_at' => now()]);

                    continue;
                }

                $title = "Season passed: {$cropKey}";
                $message = $this->aiMessage($user, 'season_passed', $cropKey, $placeLabel, null);
                $notification = $this->dispatcher->notify(
                    $user,
                    'crop_watch_season_passed',
                    $title,
                    $message,
                    [
                        'crop' => $cropKey,
                        'watchId' => $watch->id,
                        'analysis' => 'season_passed',
                        'canSetReminder' => false,
                        'location' => $placeLabel,
                    ],
                    [
                        'push' => true,
                        'preference' => 'plantingWindowAlerts',
                        'dedupeMinutes' => 10080,
                        'dedupeKey' => 'watchId',
                    ],
                );
                $watch->update([
                    'status' => 'inactive',
                    'best_plant_date' => null,
                    'last_analysis_status' => 'season_passed',
                    'last_analyzed_at' => now(),
                    'last_notified_on' => $notification ? now()->toDateString() : $watch->last_notified_on,
                ]);
                if ($notification) {
                    $sent++;
                }

                continue;
            }

            $best = $this->seasonalCalendar->bestPlantDate($cropKey, $zone);
            if (! $best) {
                $watch->update([
                    'status' => 'active',
                    'last_analysis_status' => 'waiting',
                    'last_analyzed_at' => now(),
                ]);

                continue;
            }

            // Window open / best date available — notify once only.
            if ($watch->last_analysis_status === 'window_open' && $watch->last_notified_on) {
                $watch->update([
                    'best_plant_date' => $best->toDateString(),
                    'last_analyzed_at' => now(),
                ]);

                continue;
            }

            $title = "Planting time: {$cropKey}";
            $message = $this->aiMessage($user, 'window_open', $cropKey, $placeLabel, $best->toDateString());
            $notification = $this->dispatcher->notify(
                $user,
                'crop_watch_planting',
                $title,
                $message,
                [
                    'crop' => $cropKey,
                    'watchId' => $watch->id,
                    'analysis' => 'window_open',
                    'bestPlantDate' => $best->toDateString(),
                    'canSetReminder' => true,
                    'zone' => $zone,
                    'location' => $placeLabel,
                ],
                [
                    'push' => true,
                    'preference' => 'plantingWindowAlerts',
                    'dedupeMinutes' => 10080,
                    'dedupeKey' => 'watchId',
                ],
            );

            $watch->update([
                'status' => 'active',
                'crop' => $cropKey,
                'best_plant_date' => $best->toDateString(),
                'last_analysis_status' => 'window_open',
                'last_analyzed_at' => now(),
                'last_notified_on' => $notification ? now()->toDateString() : $watch->last_notified_on,
            ]);
            if ($notification) {
                $sent++;
            }
        }

        $this->info("Sent {$sent} crop-watch notification(s).");

        return self::SUCCESS;
    }

    private function looksLikeValidCropViaAi(string $crop): bool
    {
        $trimmed = trim($crop);
        if (strlen($trimmed) < 3 || preg_match('/^\d+$/', $trimmed)) {
            return false;
        }

        $apiKey = trim(config('services.groq.api_key', ''));
        if ($apiKey === '') {
            return false;
        }

        try {
            $endpoint = trim(config('services.groq.chat_endpoint', 'https://api.groq.com/openai/v1/chat/completions'));
            $model = trim(config('services.groq.text_model', 'qwen/qwen3.6-27b'));
            $response = Http::timeout(12)
                ->withToken($apiKey)
                ->acceptJson()
                ->post($endpoint, [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Answer YES or NO only. Is this a real agricultural crop plant people farm in Nigeria?',
                        ],
                        ['role' => 'user', 'content' => $trimmed],
                    ],
                    'max_tokens' => 5,
                    'temperature' => 0,
                ]);
            $content = strtoupper(trim((string) ($response->json('choices.0.message.content') ?? '')));

            return str_starts_with($content, 'YES');
        } catch (\Throwable $e) {
            Log::warning('Crop validation AI failed', ['message' => $e->getMessage()]);

            return false;
        }
    }

    private function aiMessage($user, string $kind, string $crop, string $placeLabel, ?string $bestDate): string
    {
        $lang = $user->preferred_language ?? 'en';
        $langName = TranslationService::languageName($lang);
        $fallback = match ($kind) {
            'invalid' => "\"{$crop}\" does not look like a valid farm crop, so we removed it from your watch list.",
            'season_passed' => "The planting season for {$crop} around {$placeLabel} has already passed for this year. We kept it on your list for next year.",
            default => $bestDate
                ? "Good time to plant {$crop} around {$placeLabel}. Best planting date: {$bestDate}. You can set a reminder."
                : "It is a good window to plant {$crop} around {$placeLabel}.",
        };

        $apiKey = trim(config('services.groq.api_key', ''));
        if ($apiKey === '') {
            return $fallback;
        }

        $prompt = "Write 2 short sentences for a Nigerian farmer in {$langName}. Kind={$kind}. Crop={$crop}. "
            ."Farmer location (be specific, use this place name): {$placeLabel}. BestDate={$bestDate}. "
            .'Mention the place name so they know the app knows where they farm. Do not invent other crops.';

        try {
            $endpoint = trim(config('services.groq.chat_endpoint', 'https://api.groq.com/openai/v1/chat/completions'));
            $model = trim(config('services.groq.text_model', 'qwen/qwen3.6-27b'));
            $response = Http::timeout(12)
                ->withToken($apiKey)
                ->acceptJson()
                ->post($endpoint, [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'Short farming notifications only.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => 120,
                    'temperature' => 0.4,
                ]);
            $content = trim((string) ($response->json('choices.0.message.content') ?? ''));

            return $content !== '' ? $content : $fallback;
        } catch (\Throwable $e) {
            return $fallback;
        }
    }
}
