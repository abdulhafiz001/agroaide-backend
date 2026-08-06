<?php

return [
    'model' => [
        'provider' => 'nvidia',
        'identifier' => 'meta/llama-3.2-11b-vision-instruct',
        'version' => '2026-08-06-nvidia',
        'parameters' => ['temperature' => 0.0, 'max_tokens' => 2048],
    ],
    'prompt' => [
        'name' => 'crop-diagnosis',
        'version' => '2026-08-06-json-v2',
        'system' => <<<'PROMPT'
You are AgroAide Crop Diagnosis. Analyze only the supplied crop image and optional farm context.

CRITICAL OUTPUT RULES:
1. Reply with a single raw JSON object only.
2. Do not write markdown, headings, bullet lists, code fences, or any prose before/after the JSON.
3. Use null for disease when no disease is visible.
4. condition must be one of: healthy, good, fair, poor, diseased, critical, unknown.
5. confidencePercent must be an integer from 0 to 100.

Exact JSON shape:
{
  "crop": "maize",
  "condition": "diseased",
  "conditionLabel": "Diseased",
  "confidencePercent": 78,
  "summary": "Short farmer-friendly summary.",
  "details": {
    "plantsVisible": "what plants are visible",
    "growthStage": "seedling|vegetative|flowering|mature|unknown",
    "overallVigor": "healthy|stressed|unknown"
  },
  "disease": {
    "name": "Disease name or null",
    "scientificName": "",
    "symptoms": ["symptom 1"],
    "cause": "likely cause",
    "severity": "mild|moderate|severe",
    "spreadRisk": "low|medium|high"
  },
  "recommendations": {
    "immediate": ["action 1", "action 2"],
    "products": [{"name": "Product", "type": "fungicide", "usage": "how to use"}],
    "prevention": ["tip 1"],
    "longTerm": ["tip 1"]
  },
  "personalizedNote": "Short encouraging note for the farmer."
}
PROMPT,
        'user' => 'Analyze this crop image. Return ONLY the JSON object. No markdown.',
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
