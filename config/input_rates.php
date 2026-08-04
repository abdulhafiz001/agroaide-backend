<?php

/**
 * Nigeria-oriented seed & fertilizer guide rates (not prescriptions).
 * Spacing defaults in cm. Fertilizer rates in kg/ha. Seed via population math.
 */
return [
    'step_cm' => 75,

    'crops' => [
        'Maize' => [
            'defaultRowCm' => 75,
            'defaultIntraCm' => 25,
            'seedsPerStand' => 2,
            'seedsPerKg' => 2800,
            'fertilizers' => [
                ['name' => 'NPK 15-15-15', 'kgPerHa' => 150],
                ['name' => 'Urea', 'kgPerHa' => 100],
            ],
        ],
        'Cassava' => [
            'defaultRowCm' => 100,
            'defaultIntraCm' => 100,
            'seedsPerStand' => 1, // cuttings treated as "stands"
            'seedsPerKg' => 1, // report stands, not kg seed
            'seedUnit' => 'cuttings',
            'fertilizers' => [
                ['name' => 'NPK 15-15-15', 'kgPerHa' => 200],
            ],
        ],
        'Yam' => [
            'defaultRowCm' => 100,
            'defaultIntraCm' => 100,
            'seedsPerStand' => 1,
            'seedsPerKg' => 1,
            'seedUnit' => 'setts',
            'fertilizers' => [
                ['name' => 'NPK 15-15-15', 'kgPerHa' => 200],
            ],
        ],
        'Tomato' => [
            'defaultRowCm' => 75,
            'defaultIntraCm' => 50,
            'seedsPerStand' => 1,
            'seedsPerKg' => 300000,
            'fertilizers' => [
                ['name' => 'NPK 15-15-15', 'kgPerHa' => 250],
                ['name' => 'Urea', 'kgPerHa' => 50],
            ],
        ],
        'Rice' => [
            'defaultRowCm' => 20,
            'defaultIntraCm' => 20,
            'seedsPerStand' => 3,
            'seedsPerKg' => 40000,
            'fertilizers' => [
                ['name' => 'NPK 15-15-15', 'kgPerHa' => 200],
                ['name' => 'Urea', 'kgPerHa' => 100],
            ],
        ],
        'Sorghum' => [
            'defaultRowCm' => 75,
            'defaultIntraCm' => 20,
            'seedsPerStand' => 3,
            'seedsPerKg' => 35000,
            'fertilizers' => [
                ['name' => 'NPK 15-15-15', 'kgPerHa' => 100],
                ['name' => 'Urea', 'kgPerHa' => 50],
            ],
        ],
        'Millet' => [
            'defaultRowCm' => 75,
            'defaultIntraCm' => 20,
            'seedsPerStand' => 3,
            'seedsPerKg' => 150000,
            'fertilizers' => [
                ['name' => 'NPK 15-15-15', 'kgPerHa' => 80],
                ['name' => 'Urea', 'kgPerHa' => 40],
            ],
        ],
        'Cowpea' => [
            'defaultRowCm' => 75,
            'defaultIntraCm' => 25,
            'seedsPerStand' => 2,
            'seedsPerKg' => 4000,
            'fertilizers' => [
                ['name' => 'NPK 15-15-15', 'kgPerHa' => 50],
            ],
        ],
    ],

    'default' => [
        'defaultRowCm' => 75,
        'defaultIntraCm' => 25,
        'seedsPerStand' => 2,
        'seedsPerKg' => 5000,
        'fertilizers' => [
            ['name' => 'NPK 15-15-15', 'kgPerHa' => 100],
        ],
    ],
];
