<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;

class DemoDataFactory
{
    public static function farmOverview(User $user): array
    {
        return [
            'fields' => [
                ['id' => 'field-1', 'name' => 'North Block', 'crop' => 'Maize', 'area' => 1.8, 'health' => 82, 'moisture' => 68, 'daysSincePlanting' => 42],
                ['id' => 'field-2', 'name' => 'Cassava Ridge', 'crop' => 'Cassava', 'area' => 1.2, 'health' => 77, 'moisture' => 54, 'daysSincePlanting' => 37],
                ['id' => 'field-3', 'name' => 'Tomato Valley', 'crop' => 'Tomatoes', 'area' => 1.5, 'health' => 71, 'moisture' => 61, 'daysSincePlanting' => 29],
            ],
            'journal' => [
                [
                    'id' => 'entry-1',
                    'date' => Carbon::now()->subDay()->toIso8601String(),
                    'note' => 'Observed mild nutrient deficiency on maize Block 1.',
                    'type' => 'observation',
                ],
                [
                    'id' => 'entry-2',
                    'date' => Carbon::now()->subDays(3)->toIso8601String(),
                    'note' => 'Applied foliar feed on tomatoes.',
                    'type' => 'action',
                ],
            ],
            'map' => [
                'center' => ['latitude' => 6.8402, 'longitude' => 7.3705],
                'polygon' => [
                    ['latitude' => 6.8405, 'longitude' => 7.3690],
                    ['latitude' => 6.8408, 'longitude' => 7.3720],
                    ['latitude' => 6.8395, 'longitude' => 7.3725],
                    ['latitude' => 6.8392, 'longitude' => 7.3695],
                ],
            ],
            'farmSummary' => [
                'farmName' => $user->farm_name ?? 'My Farm',
                'farmLocation' => $user->farm_location ?? 'Unknown location',
                'farmSizeHectares' => (float) ($user->farm_size_hectares ?? 0),
            ],
        ];
    }

    public static function calendarData(): array
    {
        return [
            'optimalWindows' => [
                ['date' => Carbon::now()->addDay()->toIso8601String(), 'activity' => 'Foliar spray', 'crop' => 'Tomatoes'],
                ['date' => Carbon::now()->addDays(3)->toIso8601String(), 'activity' => 'Irrigation boost', 'crop' => 'Maize'],
            ],
            'dayPlan' => [
                ['id' => 'task-1', 'title' => 'Inspect maize for armyworm', 'period' => 'Morning', 'durationMinutes' => 40, 'impact' => 'high'],
                ['id' => 'task-2', 'title' => 'Flush drip lines', 'period' => 'Afternoon', 'durationMinutes' => 30, 'impact' => 'medium'],
                ['id' => 'task-3', 'title' => 'Update field journal', 'period' => 'Evening', 'durationMinutes' => 15, 'impact' => 'low'],
            ],
        ];
    }

    public static function weatherForecast(): array
    {
        return [
            ['day' => 'Today', 'condition' => 'Partly Cloudy', 'high' => 32, 'low' => 23, 'precipitation' => 0.10],
            ['day' => 'Tue', 'condition' => 'Thunderstorms', 'high' => 29, 'low' => 22, 'precipitation' => 0.80],
            ['day' => 'Wed', 'condition' => 'Sunny', 'high' => 34, 'low' => 21, 'precipitation' => 0.10],
            ['day' => 'Thu', 'condition' => 'Showers', 'high' => 30, 'low' => 22, 'precipitation' => 0.45],
            ['day' => 'Fri', 'condition' => 'Mostly Sunny', 'high' => 33, 'low' => 23, 'precipitation' => 0.05],
            ['day' => 'Sat', 'condition' => 'Showers', 'high' => 30, 'low' => 24, 'precipitation' => 0.40],
            ['day' => 'Sun', 'condition' => 'Cloudy', 'high' => 31, 'low' => 23, 'precipitation' => 0.25],
        ];
    }

    public static function dashboardSnapshot(User $user): array
    {
        return [
            'user' => [
                'name' => $user->name,
                'farmName' => $user->farm_name ?? 'My Farm',
            ],
            'weatherAlert' => [
                'severity' => 'Critical',
                'title' => 'Frost warning tonight',
                'advice' => 'Temperatures will dip to 4°C around 3AM. Cover sensitive crops before 8PM.',
                'gradient' => ['#ff6b6b', '#fbd786'],
            ],
            'priorityTask' => [
                'title' => 'Irrigation maintenance',
                'progress' => 72,
                'estimatedImpact' => 'Prevents stress during the coming dry spell',
                'actionItems' => ['Flush north line', 'Replace filter B', 'Log readings'],
            ],
            'soilHealth' => [
                ['label' => 'Moisture', 'value' => 68, 'unit' => '%', 'icon' => 'droplets', 'tone' => 'info'],
                ['label' => 'Nitrogen', 'value' => 42, 'unit' => 'ppm', 'icon' => 'leaf', 'tone' => 'warning'],
                ['label' => 'Soil temp', 'value' => 24, 'unit' => '°C', 'icon' => 'thermometer', 'tone' => 'neutral'],
                ['label' => 'pH level', 'value' => 6.5, 'unit' => 'pH', 'icon' => 'sprout', 'tone' => 'success'],
            ],
            'weatherForecast' => [
                ['day' => 'Today', 'high' => 32, 'low' => 23, 'precipitation' => 0.1, 'icon' => 'sun'],
                ['day' => 'Tue', 'high' => 28, 'low' => 22, 'precipitation' => 0.6, 'icon' => 'cloud-rain'],
                ['day' => 'Wed', 'high' => 30, 'low' => 21, 'precipitation' => 0.0, 'icon' => 'wind'],
                ['day' => 'Thu', 'high' => 31, 'low' => 23, 'precipitation' => 0.3, 'icon' => 'sun'],
                ['day' => 'Fri', 'high' => 27, 'low' => 20, 'precipitation' => 0.8, 'icon' => 'cloud-rain'],
            ],
            'aiInsights' => [
                [
                    'id' => 'tip-1',
                    'title' => 'Yield prediction up 4%',
                    'description' => 'Nitrogen level is recovering. Schedule foliar feed for Thursday dawn to lock-in gains.',
                ],
                [
                    'id' => 'tip-2',
                    'title' => 'Water optimization',
                    'description' => 'Reduce irrigation in Cassava Block C by 10% for the next 48hrs to save 15k L.',
                ],
            ],
        ];
    }

    public static function marketIntel(): array
    {
        return [
            'marketPrices' => [
                ['commodity' => 'Maize (White)', 'pricePerBag' => 34500, 'location' => 'Mile 12, Lagos', 'trend' => 'up'],
                ['commodity' => 'Cassava (Fresh tubers)', 'pricePerBag' => 18500, 'location' => 'Dawanau, Kano', 'trend' => 'down'],
                ['commodity' => 'Tomatoes (Basket)', 'pricePerBag' => 41000, 'location' => 'Onitsha Main Market', 'trend' => 'up'],
                ['commodity' => 'Rice (Paddy)', 'pricePerBag' => 52000, 'location' => 'Sabo, Kaduna', 'trend' => 'stable'],
                ['commodity' => 'Yam (Tuber)', 'pricePerBag' => 28000, 'location' => 'Aba Market', 'trend' => 'up'],
            ],
            'highlights' => [
                'Maize demand surging in Lagos due to feed mill restocking.',
                'Tomato prices trending upward with Ramadan demand.',
            ],
        ];
    }

    public static function advisorSuggestions(): array
    {
        return [
            'When should I plant maize?',
            'My tomatoes have yellow leaves.',
            'Best fertilizer for clay soil?',
            'Is it going to rain this week?',
        ];
    }

    public static function marketResources(): array
    {
        return [
            ['id' => 'resource-1', 'name' => 'Seed suppliers', 'location' => 'Kano'],
            ['id' => 'resource-2', 'name' => 'Equipment leasing', 'location' => 'Benue'],
            ['id' => 'resource-3', 'name' => 'Storage hubs', 'location' => 'Kaduna'],
        ];
    }

    public static function nearbyFarmers(): array
    {
        return [
            ['id' => 'farmer-1', 'name' => 'Ifeanyi Okafor', 'distanceKm' => 2.4, 'specialty' => 'Maize'],
            ['id' => 'farmer-2', 'name' => 'Zainab Bello', 'distanceKm' => 5.1, 'specialty' => 'Rice'],
            ['id' => 'farmer-3', 'name' => 'Chinwe Nnaji', 'distanceKm' => 7.8, 'specialty' => 'Vegetables'],
        ];
    }

    public static function supportLinks(): array
    {
        return [
            ['id' => 'help', 'label' => 'Help & tutorials', 'message' => 'Opening tutorials and guides...'],
            ['id' => 'extension', 'label' => 'Contact extension officer', 'message' => 'Connecting you with Enugu agent...'],
            ['id' => 'email', 'label' => 'Email support', 'message' => 'Email us: support@agroaide.ng'],
        ];
    }
}
