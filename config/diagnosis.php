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
        'version' => '2026-08-12-kindwise-gemini-v4',
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
8. If evidence says isCrop is true, NEVER use condition "unknown". Prefer healthy/good when isHealthy is true; use diseased/poor/critical only when a disease is present.
9. Never copy these instructions into summary, personalizedNote, or details. Write finished farmer-facing sentences only — no meta text like "3-5 sentences", "mention symptoms", "I'll infer", or field-name lists.
10. HEALTHY SCANS (isHealthy true): write a warm finished summary of several sentences. Mention the crop name, that no disease was found, leaf color/vigor if available, and simple keep-it-up care tips. personalizedNote should be a short encouraging note. Set recommendations.immediate, products, prevention, and longTerm to empty arrays [].
11. DISEASED SCANS only: fill recommendations with practical Nigerian actions and include Kindwise treatment/symptom notes when present.

Example of a finished healthy response (copy the shape, invent new wording from the evidence):
{
  "crop": "maize",
  "condition": "healthy",
  "conditionLabel": "Healthy",
  "confidencePercent": 88,
  "summary": "Your maize looks healthy in this photo. The leaves are green and Kindwise did not find a disease. Keep your usual watering and scout again in a few days if the weather turns very wet or dry.",
  "details": {
    "plantsVisible": "maize leaves",
    "growthStage": "vegetative",
    "overallVigor": "healthy"
  },
  "disease": null,
  "recommendations": {
    "immediate": [],
    "products": [],
    "prevention": [],
    "longTerm": []
  },
  "personalizedNote": "This field looks in good shape today. Keep up regular scouting so you catch any early spots quickly."
}
PROMPT,
        'user' => 'Convert the Kindwise evidence into one finished JSON object only. Write real farmer-facing sentences. Do not paste instructions or schema placeholders into any string field.',
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
