<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiAdvisorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvisorController extends Controller
{
    public function __construct(private AiAdvisorService $advisorService) {}

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
}
