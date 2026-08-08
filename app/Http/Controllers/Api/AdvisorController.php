<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AiAdvisorService;
use App\Services\DailyUsageLimitService;
use App\Services\VoiceTranscriptionService;
use App\Support\MediaPayloadValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdvisorController extends Controller
{
    public function __construct(
        private AiAdvisorService $advisorService,
        private VoiceTranscriptionService $voiceService,
        private MediaPayloadValidator $mediaValidator,
        private DailyUsageLimitService $dailyLimits,
    ) {}

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'language' => ['nullable', 'string', 'in:en,ha,yo,pcm'],
            'preferredLanguage' => ['nullable', 'string', 'in:en,ha,yo,pcm'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $this->dailyLimits->assertCanChat($user);
        $language = $validated['language'] ?? $validated['preferredLanguage'] ?? null;
        $reply = $this->advisorService->chat($user, trim($validated['message']), $language);

        return response()->json(['reply' => $reply]);
    }

    public function suggestions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'suggestions' => $this->advisorService->getSuggestions($user),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'messages' => $this->advisorService->history($user),
        ]);
    }

    public function dailyInsight(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'insights' => $this->advisorService->dailyInsight($user),
        ]);
    }

    public function transcribeVoice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'audioBase64' => ['required', 'string'],
            'languageHint' => ['nullable', 'string', 'max:5'],
            'language' => ['nullable', 'string', 'max:5'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $lang = $validated['languageHint'] ?? $validated['language'] ?? $user->preferred_language ?? 'en';

        try {
            $media = $this->mediaValidator->audio($validated['audioBase64']);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?: 'Invalid audio recording.';

            return response()->json(['success' => false, 'error' => $message]);
        }

        $result = $this->voiceService->transcribe($media, $lang);

        return response()->json($result);
    }
}
