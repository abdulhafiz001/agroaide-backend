<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DiseaseOutbreakService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OutbreakController extends Controller
{
    public function __construct(private DiseaseOutbreakService $outbreakService) {}

    public function heatmap(): JsonResponse
    {
        return response()->json([
            'points' => $this->outbreakService->getHeatmapData(),
        ]);
    }

    public function alerts(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json([
            'alerts' => $this->outbreakService->getAlertsForUser($user),
        ]);
    }
}
