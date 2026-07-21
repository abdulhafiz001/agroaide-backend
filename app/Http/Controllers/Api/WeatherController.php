<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeatherController extends Controller
{
    public function __construct(private WeatherService $weatherService) {}

    public function forecast(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($user->farm_latitude === null || $user->farm_longitude === null) {
            return response()->json([
                'hasFarmLocation' => false,
                'current' => [],
                'soilHealth' => [],
                'weatherForecast' => [],
                'hourly' => [],
                'alerts' => [],
            ]);
        }

        $weather = $this->weatherService->getWeather(
            (float) $user->farm_latitude,
            (float) $user->farm_longitude,
        );

        return response()->json([
            'hasFarmLocation' => true,
            'current' => $weather['current'] ?? [],
            'soilHealth' => $weather['soilHealth'] ?? [],
            'weatherForecast' => $weather['forecast'] ?? [],
            'hourly' => $weather['hourly'] ?? [],
            'alerts' => $weather['alerts'] ?? [],
        ]);
    }
}
