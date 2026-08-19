<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WeatherService
{
    private const BASE_URL = 'https://api.open-meteo.com/v1/forecast';

    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Live weather for this farmer's saved farm GPS. Never falls back to a city default.
     */
    public function getWeatherForUser(User $user): ?array
    {
        $coords = $user->farmCoordinates();
        if ($coords === null) {
            return null;
        }

        $weather = $this->getWeather($coords['latitude'], $coords['longitude']);
        $weather['farmLatitude'] = $coords['latitude'];
        $weather['farmLongitude'] = $coords['longitude'];
        $weather['farmLocation'] = $coords['label'];

        return $weather;
    }

    /**
     * Get full weather data for given coordinates.
     */
    public function getWeather(float $latitude, float $longitude): array
    {
        // Cache nearby GPS jitter (~100m) but still query Open-Meteo with the exact pin.
        $latKey = round($latitude, 3);
        $lngKey = round($longitude, 3);
        $cacheKey = "weather_{$latKey}_{$lngKey}";

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && empty($cached['_fallback'])) {
            return $cached;
        }

        $fresh = $this->fetchFromApi($latitude, $longitude);

        // Never cache fallback/unavailable responses — that was locking farmers
        // into "Weather data unavailable" for a full hour after a blip.
        if (empty($fresh['_fallback'])) {
            Cache::put($cacheKey, $fresh, self::CACHE_TTL);
        }

        return $fresh;
    }

    /**
     * Get a condensed summary suitable for the dashboard.
     */
    public function getDashboardWeather(float $latitude, float $longitude): array
    {
        $data = $this->getWeather($latitude, $longitude);

        return [
            'current' => $data['current'] ?? [],
            'soilHealth' => $data['soilHealth'] ?? [],
            'forecast' => $data['forecast'] ?? [],
            'alerts' => $data['alerts'] ?? [],
            'isLive' => empty($data['_fallback']),
        ];
    }

    private function fetchFromApi(float $latitude, float $longitude): array
    {
        $response = Http::timeout(15)->connectTimeout(5)->get(self::BASE_URL, [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'current' => implode(',', [
                'temperature_2m',
                'relative_humidity_2m',
                'apparent_temperature',
                'precipitation',
                'weather_code',
                'wind_speed_10m',
                'is_day',
            ]),
            'hourly' => implode(',', [
                'temperature_2m',
                'precipitation_probability',
                'weather_code',
                'soil_temperature_0cm',
                'soil_moisture_0_to_1cm',
            ]),
            'daily' => implode(',', [
                'weather_code',
                'temperature_2m_max',
                'temperature_2m_min',
                'precipitation_sum',
                'precipitation_probability_max',
                'uv_index_max',
                'sunrise',
                'sunset',
            ]),
            'timezone' => 'auto',
            'forecast_days' => 7,
        ]);

        if (! $response->successful()) {
            return $this->fallbackData();
        }

        $json = $response->json();
        if (! is_array($json) || empty($json['current'])) {
            return $this->fallbackData();
        }

        return [
            'current' => $this->parseCurrent($json),
            'soilHealth' => $this->parseSoilData($json),
            'forecast' => $this->parseDailyForecast($json),
            'hourly' => $this->parseHourly($json),
            'alerts' => $this->generateAlerts($json),
            '_fallback' => false,
        ];
    }

    private function parseCurrent(array $json): array
    {
        $current = $json['current'] ?? [];

        return [
            'temperature' => $current['temperature_2m'] ?? null,
            'humidity' => $current['relative_humidity_2m'] ?? null,
            'apparentTemperature' => $current['apparent_temperature'] ?? null,
            'precipitation' => $current['precipitation'] ?? 0,
            'weatherCode' => $current['weather_code'] ?? 0,
            'windSpeed' => $current['wind_speed_10m'] ?? null,
            'isDay' => ($current['is_day'] ?? 1) === 1,
            'condition' => $this->weatherCodeToCondition($current['weather_code'] ?? 0),
            'icon' => $this->weatherCodeToIcon($current['weather_code'] ?? 0),
        ];
    }

    private function parseSoilData(array $json): array
    {
        $hourly = $json['hourly'] ?? [];
        $currentHourIndex = (int) date('G');

        $soilTemp = ($hourly['soil_temperature_0cm'] ?? [])[$currentHourIndex] ?? null;
        $soilMoisture = ($hourly['soil_moisture_0_to_1cm'] ?? [])[$currentHourIndex] ?? null;

        $moisturePct = $soilMoisture !== null ? round($soilMoisture * 100) : null;

        return [
            [
                'label' => 'Moisture',
                'value' => $moisturePct ?? 50,
                'unit' => '%',
                'icon' => 'droplets',
                'tone' => $this->getMoistureTone($moisturePct),
            ],
            [
                'label' => 'Soil temp',
                'value' => $soilTemp !== null ? round($soilTemp, 1) : 24,
                'unit' => '°C',
                'icon' => 'thermometer',
                'tone' => $this->getSoilTempTone($soilTemp),
            ],
            [
                'label' => 'Humidity',
                'value' => ($json['current']['relative_humidity_2m'] ?? 60),
                'unit' => '%',
                'icon' => 'cloud',
                'tone' => 'info',
            ],
            [
                'label' => 'Wind',
                'value' => round($json['current']['wind_speed_10m'] ?? 5, 1),
                'unit' => 'km/h',
                'icon' => 'wind',
                'tone' => 'neutral',
            ],
        ];
    }

    private function parseDailyForecast(array $json): array
    {
        $daily = $json['daily'] ?? [];
        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $forecast = [];

        $dates = $daily['time'] ?? [];
        $maxTemps = $daily['temperature_2m_max'] ?? [];
        $minTemps = $daily['temperature_2m_min'] ?? [];
        $precipitations = $daily['precipitation_sum'] ?? [];
        $precipProbabilities = $daily['precipitation_probability_max'] ?? [];
        $weatherCodes = $daily['weather_code'] ?? [];
        $uvIndexes = $daily['uv_index_max'] ?? [];

        for ($i = 0; $i < min(7, count($dates)); $i++) {
            $dayName = $i === 0 ? 'Today' : $days[(int) date('w', strtotime($dates[$i]))];
            $code = $weatherCodes[$i] ?? 0;

            $forecast[] = [
                'day' => $dayName,
                'date' => $dates[$i] ?? null,
                'high' => round($maxTemps[$i] ?? 30),
                'low' => round($minTemps[$i] ?? 22),
                'precipitation' => round(($precipitations[$i] ?? 0), 1),
                'precipitationProbability' => (int) round($precipProbabilities[$i] ?? 0),
                'condition' => $this->weatherCodeToCondition($code),
                'icon' => $this->weatherCodeToIcon($code),
                'uvIndex' => $uvIndexes[$i] ?? 0,
            ];
        }

        return $forecast;
    }

    private function parseHourly(array $json): array
    {
        $hourly = $json['hourly'] ?? [];
        $temps = $hourly['temperature_2m'] ?? [];
        $precip = $hourly['precipitation_probability'] ?? [];
        $codes = $hourly['weather_code'] ?? [];
        $times = $hourly['time'] ?? [];

        $result = [];
        $currentHour = (int) date('G');

        for ($i = $currentHour; $i < min($currentHour + 24, count($times)); $i++) {
            $result[] = [
                'time' => $times[$i] ?? null,
                'temperature' => $temps[$i] ?? null,
                'precipitationProbability' => $precip[$i] ?? 0,
                'condition' => $this->weatherCodeToCondition($codes[$i] ?? 0),
            ];
        }

        return $result;
    }

    private function generateAlerts(array $json): array
    {
        $alerts = [];
        $daily = $json['daily'] ?? [];
        $current = $json['current'] ?? [];
        $hourly = $json['hourly'] ?? [];

        $todayPrecip = ($daily['precipitation_sum'] ?? [])[0] ?? 0;
        $todayMax = ($daily['temperature_2m_max'] ?? [])[0] ?? 30;
        $todayUv = ($daily['uv_index_max'] ?? [])[0] ?? 0;
        $windSpeed = $current['wind_speed_10m'] ?? 0;
        $highRainChance = $this->findHighRainProbability($hourly);

        if ($highRainChance !== null) {
            $alerts[] = [
                'severity' => 'Critical',
                'title' => 'High chance of rain soon',
                'advice' => "{$highRainChance['probability']}% chance of rain around {$highRainChance['timeLabel']}. Pause irrigation and plan field work carefully.",
                'gradient' => ['#4a90e2', '#357abd'],
                'alertKey' => 'rain_probability_90',
            ];
        }

        if ($todayPrecip > 20) {
            $alerts[] = [
                'severity' => 'Critical',
                'title' => 'Heavy rainfall expected',
                'advice' => "Expected {$todayPrecip}mm of rain today. Ensure proper drainage and avoid field work during peak rain hours.",
                'gradient' => ['#4a90e2', '#357abd'],
                'alertKey' => 'heavy_rain',
            ];
        } elseif ($todayPrecip > 5) {
            $alerts[] = [
                'severity' => 'Moderate',
                'title' => 'Rain expected today',
                'advice' => "About {$todayPrecip}mm of rain expected. Good for irrigation savings — pause watering schedules.",
                'gradient' => ['#4a90e2', '#7bb3f0'],
                'alertKey' => 'rain_today',
            ];
        }

        if ($todayMax > 35) {
            $alerts[] = [
                'severity' => 'Critical',
                'title' => 'High temperature warning',
                'advice' => "Temperatures reaching {$todayMax}°C. Water crops early morning. Provide shade for sensitive transplants.",
                'gradient' => ['#ff6b6b', '#fbd786'],
                'alertKey' => 'high_temp',
            ];
        }

        if ($todayUv > 8) {
            $alerts[] = [
                'severity' => 'Moderate',
                'title' => 'High UV index',
                'advice' => "UV index at {$todayUv}. Avoid extended field work between 11AM-3PM. Use protective gear.",
                'gradient' => ['#f39c12', '#e74c3c'],
                'alertKey' => 'high_uv',
            ];
        }

        if ($windSpeed > 30) {
            $alerts[] = [
                'severity' => 'Moderate',
                'title' => 'Strong winds',
                'advice' => "Wind speed at {$windSpeed} km/h. Secure loose structures and delay spraying activities.",
                'gradient' => ['#95a5a6', '#7f8c8d'],
                'alertKey' => 'strong_wind',
            ];
        }

        if (empty($alerts)) {
            $alerts[] = [
                'severity' => 'Low',
                'title' => 'Good farming conditions',
                'advice' => 'Weather looks favorable today. Great conditions for field work and planting activities.',
                'gradient' => ['#2eb873', '#57b346'],
                'alertKey' => 'good_conditions',
            ];
        }

        return $alerts;
    }

    /**
     * Find ≥90% precipitation probability within the next 12 hours.
     *
     * @return array{probability: int, timeLabel: string}|null
     */
    private function findHighRainProbability(array $hourly): ?array
    {
        $probabilities = $hourly['precipitation_probability'] ?? [];
        $times = $hourly['time'] ?? [];
        if ($probabilities === [] || $times === []) {
            return null;
        }

        $now = now();
        $horizon = now()->addHours(12);
        $best = null;

        foreach ($times as $index => $time) {
            $probability = (int) ($probabilities[$index] ?? 0);
            if ($probability < 90) {
                continue;
            }

            try {
                $at = Carbon::parse($time);
            } catch (\Throwable) {
                continue;
            }

            if ($at->lt($now) || $at->gt($horizon)) {
                continue;
            }

            if ($best === null || $probability > $best['probability']) {
                $best = [
                    'probability' => $probability,
                    'timeLabel' => $at->format('g A'),
                ];
            }
        }

        return $best;
    }

    private function getMoistureTone(?float $moisture): string
    {
        if ($moisture === null) {
            return 'neutral';
        }
        if ($moisture < 20) {
            return 'danger';
        }
        if ($moisture < 40) {
            return 'warning';
        }
        if ($moisture > 80) {
            return 'warning';
        }

        return 'info';
    }

    private function getSoilTempTone(?float $temp): string
    {
        if ($temp === null) {
            return 'neutral';
        }
        if ($temp < 10 || $temp > 40) {
            return 'danger';
        }
        if ($temp < 15 || $temp > 35) {
            return 'warning';
        }

        return 'neutral';
    }

    private function weatherCodeToCondition(int $code): string
    {
        return match (true) {
            $code === 0 => 'Clear sky',
            $code <= 3 => 'Partly cloudy',
            in_array($code, [45, 48]) => 'Foggy',
            in_array($code, [51, 53, 55]) => 'Light drizzle',
            in_array($code, [56, 57]) => 'Freezing drizzle',
            in_array($code, [61, 63, 65]) => 'Rain',
            in_array($code, [66, 67]) => 'Freezing rain',
            in_array($code, [71, 73, 75, 77]) => 'Snow',
            in_array($code, [80, 81, 82]) => 'Showers',
            in_array($code, [85, 86]) => 'Snow showers',
            in_array($code, [95, 96, 99]) => 'Thunderstorms',
            default => 'Unknown',
        };
    }

    private function weatherCodeToIcon(int $code): string
    {
        return match (true) {
            $code === 0 => 'sun',
            $code <= 3 => 'cloud-sun',
            in_array($code, [45, 48]) => 'cloud-fog',
            in_array($code, [51, 53, 55, 56, 57]) => 'cloud-drizzle',
            in_array($code, [61, 63, 65, 66, 67]) => 'cloud-rain',
            in_array($code, [71, 73, 75, 77, 85, 86]) => 'snowflake',
            in_array($code, [80, 81, 82]) => 'cloud-rain',
            in_array($code, [95, 96, 99]) => 'cloud-lightning',
            default => 'cloud',
        };
    }

    private function fallbackData(): array
    {
        return [
            '_fallback' => true,
            'current' => [],
            'soilHealth' => [],
            'forecast' => [],
            'hourly' => [],
            'alerts' => [[
                'severity' => 'Low',
                'title' => 'Weather data unavailable',
                'advice' => 'Unable to fetch live weather data right now. Pull to refresh in a moment.',
                'gradient' => ['#95a5a6', '#7f8c8d'],
            ]],
        ];
    }
}
