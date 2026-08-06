<?php

return [
    'model' => [
        'provider' => 'github-models',
        'identifier' => 'openai/gpt-4o-mini',
        'version' => '2026-08-06',
        'parameters' => ['temperature' => 0.0, 'max_tokens' => 2048],
    ],
    'prompt' => [
        'name' => 'crop-diagnosis',
        'version' => '2026-08-06',
        'system' => <<<'PROMPT'
You are AgroAide Crop Diagnosis. Analyze only the supplied crop image and optional farm context. Do not infer a ground-truth label. Return one JSON object with crop, condition, disease, confidencePercent, summary, details, and recommendations. Use null when crop or disease cannot be identified. Confidence must reflect visual evidence.
PROMPT,
        'user' => 'Analyze this crop image. Return only valid JSON.',
    ],
    'confidence_policy' => [
        'name' => 'production-default',
        'version' => '2026-08-06',
        'retake_below' => 0.60,
        'review_below' => 0.85,
        'require_canonical' => true,
    ],
    'labels' => [
        'crops' => [
            'maize' => ['Maize', 'corn'],
            'cassava' => ['Cassava', 'manioc'],
            'rice' => ['Rice', 'paddy'],
            'yam' => ['Yam'],
            'tomato' => ['Tomato', 'tomatoes'],
            'pepper' => ['Pepper', 'chilli', 'chili'],
            'cowpea' => ['Cowpea', 'beans', 'black-eyed pea'],
            'groundnut' => ['Groundnut', 'peanut'],
            'plantain' => ['Plantain'],
        ],
        'diseases' => [
            'healthy' => ['Healthy', 'no disease'],
            'maize-leaf-blight' => ['Maize Leaf Blight', 'northern corn leaf blight'],
            'maize-rust' => ['Maize Rust', 'common rust'],
            'cassava-mosaic-disease' => ['Cassava Mosaic Disease', 'cassava mosaic'],
            'cassava-brown-streak-disease' => ['Cassava Brown Streak Disease', 'brown streak'],
            'rice-blast' => ['Rice Blast', 'blast disease'],
            'yam-anthracnose' => ['Yam Anthracnose', 'anthracnose'],
            'tomato-late-blight' => ['Tomato Late Blight', 'late blight'],
            'tomato-early-blight' => ['Tomato Early Blight', 'early blight'],
            'pepper-leaf-spot' => ['Pepper Leaf Spot', 'bacterial leaf spot'],
            'cowpea-mosaic-virus' => ['Cowpea Mosaic Virus', 'cowpea mosaic'],
            'groundnut-rosette' => ['Groundnut Rosette Disease', 'groundnut rosette'],
            'black-sigatoka' => ['Black Sigatoka', 'black leaf streak'],
        ],
    ],
];
