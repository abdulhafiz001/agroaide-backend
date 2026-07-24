<?php

/**
 * Nigeria agro-ecological zones and seasonal crop calendar (no LLM).
 *
 * Zones by latitude:
 * - humid_forest: lat < 7.5
 * - guinea_savanna: 7.5 <= lat < 11
 * - sudan_sahel: lat >= 11
 */
return [
    'zones' => [
        'humid_forest' => [
            'label' => 'Humid Forest',
            'latMax' => 7.5,
            'rainyMonths' => [3, 4, 5, 6, 7, 9, 10, 11],
        ],
        'guinea_savanna' => [
            'label' => 'Guinea Savanna',
            'latMin' => 7.5,
            'latMax' => 11.0,
            'rainyMonths' => [4, 5, 6, 7, 8, 9, 10],
        ],
        'sudan_sahel' => [
            'label' => 'Sudan-Sahel',
            'latMin' => 11.0,
            'rainyMonths' => [5, 6, 7, 8, 9],
        ],
    ],

    /**
     * plantingMonths: month numbers (1-12) per zone when planting is recommended.
     * stageOffsets: days from planted_at for key stages.
     */
    'crops' => [
        'Maize' => [
            'plantingMonths' => [
                'humid_forest' => [3, 4, 5, 9, 10],
                'guinea_savanna' => [4, 5, 6],
                'sudan_sahel' => [5, 6, 7],
            ],
            'stageOffsets' => [
                'land_prep' => -14,
                'plant' => 0,
                'fertilize' => 21,
                'weed' => 28,
                'harvest' => 100,
            ],
        ],
        'Cassava' => [
            'plantingMonths' => [
                'humid_forest' => [3, 4, 5, 9, 10],
                'guinea_savanna' => [4, 5, 6],
                'sudan_sahel' => [5, 6],
            ],
            'stageOffsets' => [
                'land_prep' => -21,
                'plant' => 0,
                'fertilize' => 45,
                'weed' => 60,
                'harvest' => 300,
            ],
        ],
        'Yam' => [
            'plantingMonths' => [
                'humid_forest' => [2, 3, 4],
                'guinea_savanna' => [3, 4, 5],
                'sudan_sahel' => [4, 5],
            ],
            'stageOffsets' => [
                'land_prep' => -30,
                'plant' => 0,
                'fertilize' => 60,
                'weed' => 45,
                'harvest' => 240,
            ],
        ],
        'Tomato' => [
            'plantingMonths' => [
                'humid_forest' => [3, 4, 9, 10],
                'guinea_savanna' => [4, 5, 9],
                'sudan_sahel' => [5, 6, 10],
            ],
            'stageOffsets' => [
                'land_prep' => -10,
                'plant' => 0,
                'fertilize' => 14,
                'weed' => 21,
                'harvest' => 75,
            ],
        ],
        'Rice' => [
            'plantingMonths' => [
                'humid_forest' => [4, 5, 6],
                'guinea_savanna' => [5, 6, 7],
                'sudan_sahel' => [6, 7],
            ],
            'stageOffsets' => [
                'land_prep' => -21,
                'plant' => 0,
                'fertilize' => 30,
                'weed' => 35,
                'harvest' => 120,
            ],
        ],
        'Sorghum' => [
            'plantingMonths' => [
                'humid_forest' => [4, 5],
                'guinea_savanna' => [5, 6, 7],
                'sudan_sahel' => [5, 6, 7],
            ],
            'stageOffsets' => [
                'land_prep' => -14,
                'plant' => 0,
                'fertilize' => 25,
                'weed' => 30,
                'harvest' => 110,
            ],
        ],
        'Millet' => [
            'plantingMonths' => [
                'humid_forest' => [4, 5],
                'guinea_savanna' => [5, 6],
                'sudan_sahel' => [5, 6, 7],
            ],
            'stageOffsets' => [
                'land_prep' => -10,
                'plant' => 0,
                'fertilize' => 20,
                'weed' => 25,
                'harvest' => 90,
            ],
        ],
        'Cowpea' => [
            'plantingMonths' => [
                'humid_forest' => [4, 5, 8, 9],
                'guinea_savanna' => [5, 6, 7],
                'sudan_sahel' => [6, 7],
            ],
            'stageOffsets' => [
                'land_prep' => -7,
                'plant' => 0,
                'fertilize' => 14,
                'weed' => 21,
                'harvest' => 70,
            ],
        ],
    ],
];
