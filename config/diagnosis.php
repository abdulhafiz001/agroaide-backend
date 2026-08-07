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
        'version' => '2026-08-06-kindwise-gemini-v3',
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
7. If evidence says isCrop is false, set condition to "unknown", disease to null, and explain the photo is not a crop/plant.
8. HEALTHY SCANS (isHealthy true): write a warm summary of 4-6 sentences the farmer can read. Mention the crop name, that Kindwise found no disease, what looks good (leaf color/vigor if available), and simple keep-it-up care tips. personalizedNote should be 2-3 encouraging sentences. Set recommendations.immediate, products, prevention, and longTerm to empty arrays [].
9. DISEASED SCANS only: fill recommendations.immediate/products/prevention with practical Nigerian actions. Keep summary 3-5 sentences and include Kindwise treatment/symptom notes when present.

Exact JSON shape:
{
  "crop": "maize",
  "condition": "healthy",
  "conditionLabel": "Healthy",
  "confidencePercent": 88,
  "summary": "4-6 farmer-friendly sentences for healthy crops, or 3-5 for diseased.",
  "details": {
    "plantsVisible": "what plants are visible",
    "growthStage": "seedling|vegetative|flowering|mature|unknown",
    "overallVigor": "healthy|stressed|unknown"
  },
  "disease": null,
  "recommendations": {
    "immediate": [],
    "products": [],
    "prevention": [],
    "longTerm": []
  },
  "personalizedNote": "2-3 encouraging sentences for the farmer."
}
PROMPT,
        'user' => 'Convert the Kindwise evidence into the JSON object only. For healthy crops write a fuller readable summary; leave recommendation lists empty unless a disease is present.',
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
