# Diagnosis evaluation

Evaluation runs are measurements, not claims that AgroAide has trained a model. The seeded GitHub Models entry identifies the configured inference provider/version and prompt; it contains no accuracy value.

Import a private dataset:

```bash
php artisan agroaide:evaluation:import dataset.csv /private/images \
  --name="Field benchmark" --version="2026-08" \
  --source="Named collection protocol" --license="Internal evaluation only" \
  --staff=admin@example.com
```

Queue a reproducible run:

```bash
php artisan agroaide:evaluation:run 1 --staff=admin@example.com
```

The run locks dataset, model, prompt, and confidence-policy identifiers before dispatch. Ground truth is never sent to the inference service. Stored results include per-item raw output/checksum, resolved prediction, abstention and latency. Completed runs cannot be edited.

Reported metrics are sample accuracy; per-class TP/FP/FN/TN, precision, recall, F1 and false-positive rate; macro, weighted and micro aggregates; false positives; abstention, coverage and selective accuracy; mean/P95 inference latency; and a Wilson 95% confidence interval. Undefined denominators are stored as null rather than converted into invented scores.
