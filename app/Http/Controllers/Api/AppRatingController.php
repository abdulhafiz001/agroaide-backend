<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppRating;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppRatingController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'stars' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'source' => ['nullable', 'string', 'max:40'],
            'dismissed' => ['nullable', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! empty($validated['dismissed'])) {
            $user->update(['app_rating_prompt_status' => 'dismissed']);

            return response()->json([
                'message' => 'Rating prompt dismissed.',
                'shouldPromptRating' => false,
            ]);
        }

        AppRating::create([
            'user_id' => $user->id,
            'stars' => (int) $validated['stars'],
            'comment' => $validated['comment'] ?? null,
            'source' => $validated['source'] ?? 'post_harvest',
        ]);

        $user->update(['app_rating_prompt_status' => 'completed']);

        return response()->json([
            'message' => 'Thanks for your rating.',
            'shouldPromptRating' => false,
        ]);
    }
}
