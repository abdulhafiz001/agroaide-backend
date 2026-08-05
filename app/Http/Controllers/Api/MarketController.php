<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MarketPriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketController extends Controller
{
    public function __construct(private MarketPriceService $marketPrices) {}

    public function intel(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $crop = $request->query('crop');

        $payload = $this->marketPrices->intelForUser(
            $user,
            is_string($crop) && $crop !== '' ? $crop : null,
        );

        return response()->json($payload);
    }
}
