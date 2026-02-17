<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiAdvisorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketController extends Controller
{
    public function __construct(private AiAdvisorService $advisorService) {}

    public function intel(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $crops = is_array($user->crops) && ! empty($user->crops)
            ? $user->crops
            : ['Maize', 'Rice', 'Cassava', 'Tomatoes', 'Yam'];

        $marketData = $this->advisorService->estimateMarketPrices($user, $crops);

        return response()->json([
            'marketPrices' => $marketData['prices'] ?? [],
            'highlights' => $marketData['highlights'] ?? [],
            'lastUpdated' => now()->toIso8601String(),
            'source' => 'AI-estimated based on market trends',
        ]);
    }

    public function nearbyFarmers(Request $request): JsonResponse
    {
        $location = $request->user()->farm_location ?? 'Nigeria';

        return response()->json([
            'farmers' => [],
            'message' => "Community features coming soon for {$location}.",
        ]);
    }

    public function resources(): JsonResponse
    {
        return response()->json([
            'resources' => [
                ['id' => 'r1', 'name' => 'Agricultural extension services', 'description' => 'Connect with local agricultural officers'],
                ['id' => 'r2', 'name' => 'Seed & input suppliers', 'description' => 'Find certified seed suppliers near you'],
                ['id' => 'r3', 'name' => 'Storage facilities', 'description' => 'Locate crop storage hubs in your region'],
            ],
        ]);
    }
}
