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

        $lat = $user->farm_latitude ?? 6.8402;
        $lng = $user->farm_longitude ?? 7.3705;

        $weather = $this->weatherService->getWeather((float) $lat, (float) $lng);

        return response()->json([
            'current' => $weather['current'] ?? [],
            'soilHealth' => $weather['soilHealth'] ?? [],
            'weatherForecast' => $weather['forecast'] ?? [],
            'hourly' => $weather['hourly'] ?? [],
            'alerts' => $weather['alerts'] ?? [],
        ]);
    }
}
