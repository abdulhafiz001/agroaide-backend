<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiAdvisorService;
use App\Services\VoiceTranscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvisorController extends Controller
{
    public function __construct(
        private AiAdvisorService $advisorService,
        private VoiceTranscriptionService $voiceService,
    ) {}

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $reply = $this->advisorService->chat($user, trim($validated['message']));

        return response()->json(['reply' => $reply]);
    }

    public function suggestions(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json([
            'suggestions' => $this->advisorService->getSuggestions($user),
        ]);
    }

    public function dailyInsight(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
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
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $lang = $validated['languageHint'] ?? $user->preferred_language ?? 'en';

        $result = $this->voiceService->transcribe($validated['audioBase64'], $lang);

        return response()->json($result);
    }
}
