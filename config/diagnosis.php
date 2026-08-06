<?php

return [
    'model' => [
        'provider' => 'kindwise',
        'identifier' => 'crop.health',
        'version' => '2026-08-06-kindwise',
        'parameters' => ['temperature' => 0.2, 'max_tokens' => 2048],
    ],
    'prompt' => [
        'name' => 'crop-diagnosis',
        'version' => '2026-08-06-kindwise-gemini-v1',
        'system' => <<<'PROMPT'
You are AgroAide Crop Diagnosis writer. You receive Kindwise crop.health research-backed identification evidence.
Turn that evidence into a clear farmer-facing result for Nigerian smallholders.

CRITICAL OUTPUT RULES:
1. Reply with a single raw JSON object only.
2. No markdown, headings, code fences, thinking, or prose outside JSON.
3. Treat Kindwise crop/disease suggestions as ground truth. Do not invent a different disease.
4. Use null for disease when Kindwise indicates healthy / no disease.
5. condition must be one of: healthy, good, fair, poor, diseased, critical, unknown.
6. confidencePercent must be an integer 0-100 aligned with Kindwise probability.
7. Recommendations must be practical for Nigeria (local products/practices when possible).

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
        'user' => 'Convert the Kindwise evidence into the JSON object only.',
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
