<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeatherController extends Controller
{
    public function __construct(private WeatherService $weatherService) {}

    public function forecast(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $coords = $user->farmCoordinates();
        if ($coords === null) {
            return response()->json([
                'hasFarmLocation' => false,
                'farmLatitude' => null,
                'farmLongitude' => null,
                'farmLocation' => null,
                'current' => [],
                'soilHealth' => [],
                'weatherForecast' => [],
                'hourly' => [],
                'alerts' => [],
            ]);
        }

        $weather = $this->weatherService->getWeatherForUser($user) ?? [];

        return response()->json([
            'hasFarmLocation' => true,
            'farmLatitude' => $coords['latitude'],
            'farmLongitude' => $coords['longitude'],
            'farmLocation' => $coords['label'],
            'current' => $weather['current'] ?? [],
            'soilHealth' => $weather['soilHealth'] ?? [],
            'weatherForecast' => $weather['forecast'] ?? [],
            'hourly' => $weather['hourly'] ?? [],
            'alerts' => $weather['alerts'] ?? [],
        ]);
    }
}
