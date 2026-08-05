<?php

/**
 * Market Eye integration — crowd-verified Nigerian market prices.
 * @see https://marketeye.ahzcode.sbs/developers
 */
return [
    'base_url' => env('MARKETEYE_BASE_URL', 'https://marketeye.ahzcode.sbs/api/v1/public'),
    'api_key' => env('MARKETEYE_API_KEY', ''),
    'markets_cache_ttl' => 86400, // 24h
    'sync_lock_seconds' => 120,

    /**
     * Fallback coordinates when Market Eye returns null lat/lng.
     * Approximate open-market locations (Abuja metro).
     */
    'market_coords' => [
        1 => ['lat' => 9.0765, 'lng' => 7.3986], // Wuse
        2 => ['lat' => 9.0260, 'lng' => 7.5690], // Nyanya
        3 => ['lat' => 9.0000, 'lng' => 7.5800], // Maraba
        4 => ['lat' => 9.0160, 'lng' => 7.5760], // Karu
    ],

    /**
     * Map AgroAide crop names → preferred Market Eye product name fragments (priority order).
     */
    'crop_aliases' => [
        'Rice' => ['rice (local)', 'rice local', 'rice'],
        'Maize' => ['maize', 'corn'],
        'Cassava' => ['cassava', 'garri'],
        'Yam' => ['yam'],
        'Tomato' => ['tomato', 'tomatoes'],
        'Tomatoes' => ['tomato', 'tomatoes'],
        'Beans' => ['beans', 'beans (brown)', 'cowpea'],
        'Cowpea' => ['cowpea', 'beans'],
        'Sorghum' => ['sorghum'],
        'Millet' => ['millet'],
        'Groundnut' => ['groundnut', 'peanut'],
        'Soybean' => ['soybean', 'soya'],
    ],
];
