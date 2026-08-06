<?php

namespace App\Services;

class EvaluationMetricsCalculator
{
    /**
     * @param  array<int, array{truth:string,prediction:?string,latency_ms?:int|float|null}>  $rows
     * @param  array<int, string>  $labels
     */
    public function calculate(array $rows, array $labels = []): array
    {
        $total = count($rows);
        $covered = count(array_filter($rows, fn ($row) => filled($row['prediction'] ?? null)));
        $correct = count(array_filter($rows, fn ($row) => ($row['prediction'] ?? null) === $row['truth']));
        $coveredCorrect = count(array_filter($rows, fn ($row) => filled($row['prediction'] ?? null) && $row['prediction'] === $row['truth']));
        $labels = array_values(array_unique([...$labels, ...array_column($rows, 'truth')]));

        $classes = [];
        foreach ($labels as $label) {
            $tp = $fp = $fn = $tn = 0;
            foreach ($rows as $row) {
                $truth = $row['truth'] === $label;
                $predicted = ($row['prediction'] ?? null) === $label;
                $truth && $predicted ? $tp++ : (! $truth && $predicted ? $fp++ : ($truth ? $fn++ : $tn++));
            }
            $precision = $this->divide($tp, $tp + $fp);
            $recall = $this->divide($tp, $tp + $fn);
            $classes[$label] = [
                'tp' => $tp, 'fp' => $fp, 'fn' => $fn, 'tn' => $tn,
                'precision' => $precision,
                'recall' => $recall,
                'f1' => ($precision === null || $recall === null || $precision + $recall === 0.0)
                    ? 0.0 : 2 * $precision * $recall / ($precision + $recall),
                'fpr' => $this->divide($fp, $fp + $tn),
                'support' => $tp + $fn,
            ];
        }

        $latencies = array_values(array_map('floatval', array_filter(
            array_column($rows, 'latency_ms'),
            fn ($value) => $value !== null,
        )));
        sort($latencies);
        $accuracy = $this->divide($correct, $total);
        [$lower, $upper] = $this->wilson($correct, $total);

        return [
            'sample_count' => $total,
            'accuracy' => $accuracy,
            'false_positives' => array_sum(array_column($classes, 'fp')),
            'abstention_rate' => $total ? ($total - $covered) / $total : null,
            'coverage' => $this->divide($covered, $total),
            'selective_accuracy' => $this->divide($coveredCorrect, $covered),
            'macro' => $this->aggregateMacro($classes),
            'weighted' => $this->aggregateWeighted($classes),
            'micro' => $this->aggregateMicro($classes),
            'classes' => $classes,
            'latency' => [
                'mean_ms' => $latencies ? array_sum($latencies) / count($latencies) : null,
                'p95_ms' => $latencies ? $latencies[(int) ceil(count($latencies) * 0.95) - 1] : null,
            ],
            'wilson_95_ci' => [$lower, $upper],
        ];
    }

    private function aggregateMacro(array $classes): array
    {
        return $this->averages($classes, fn () => 1.0);
    }

    private function aggregateWeighted(array $classes): array
    {
        return $this->averages($classes, fn ($metric) => (float) $metric['support']);
    }

    private function aggregateMicro(array $classes): array
    {
        $tp = array_sum(array_column($classes, 'tp'));
        $fp = array_sum(array_column($classes, 'fp'));
        $fn = array_sum(array_column($classes, 'fn'));
        $precision = $this->divide($tp, $tp + $fp);
        $recall = $this->divide($tp, $tp + $fn);

        return [
            'precision' => $precision,
            'recall' => $recall,
            'f1' => ($precision === null || $recall === null || $precision + $recall === 0.0)
                ? 0.0 : 2 * $precision * $recall / ($precision + $recall),
        ];
    }

    private function averages(array $classes, callable $weight): array
    {
        $result = [];
        foreach (['precision', 'recall', 'f1', 'fpr'] as $key) {
            $sum = $weights = 0.0;
            foreach ($classes as $metric) {
                if ($metric[$key] === null) {
                    continue;
                }
                $w = $weight($metric);
                $sum += $metric[$key] * $w;
                $weights += $w;
            }
            $result[$key] = $weights > 0 ? $sum / $weights : null;
        }

        return $result;
    }

    private function divide(int $numerator, int $denominator): ?float
    {
        return $denominator === 0 ? null : $numerator / $denominator;
    }

    private function wilson(int $successes, int $total): array
    {
        if ($total === 0) {
            return [null, null];
        }
        $z = 1.959963984540054;
        $p = $successes / $total;
        $denominator = 1 + ($z ** 2 / $total);
        $center = ($p + ($z ** 2 / (2 * $total))) / $denominator;
        $margin = ($z / $denominator) * sqrt(($p * (1 - $p) / $total) + ($z ** 2 / (4 * $total ** 2)));

        return [max(0.0, $center - $margin), min(1.0, $center + $margin)];
    }
}
