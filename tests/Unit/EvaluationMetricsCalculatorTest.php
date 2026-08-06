<?php

namespace Tests\Unit;

use App\Services\EvaluationMetricsCalculator;
use PHPUnit\Framework\TestCase;

class EvaluationMetricsCalculatorTest extends TestCase
{
    public function test_binary_and_aggregate_metrics_are_calculated_with_zero_safe_denominators(): void
    {
        $metrics = (new EvaluationMetricsCalculator)->calculate([
            ['truth' => 'blight', 'prediction' => 'blight', 'latency_ms' => 100],
            ['truth' => 'healthy', 'prediction' => 'blight', 'latency_ms' => 200],
            ['truth' => 'blight', 'prediction' => null, 'latency_ms' => 300],
            ['truth' => 'healthy', 'prediction' => 'healthy', 'latency_ms' => 400],
        ], ['blight', 'healthy']);

        $this->assertSame(0.5, $metrics['accuracy']);
        $this->assertSame(0.75, $metrics['coverage']);
        $this->assertEqualsWithDelta(2 / 3, $metrics['selective_accuracy'], 0.000001);
        $this->assertSame(0.25, $metrics['abstention_rate']);
        $this->assertSame(250.0, $metrics['latency']['mean_ms']);
        $this->assertSame(400.0, $metrics['latency']['p95_ms']);
        $this->assertSame(['tp' => 1, 'fp' => 1, 'fn' => 1, 'tn' => 1], array_intersect_key(
            $metrics['classes']['blight'],
            array_flip(['tp', 'fp', 'fn', 'tn']),
        ));
        $this->assertSame(0.0, $metrics['classes']['missing']['precision'] ?? 0.0);
        $this->assertCount(2, $metrics['wilson_95_ci']);
    }

    public function test_empty_dataset_returns_null_rates_instead_of_fabricated_accuracy(): void
    {
        $metrics = (new EvaluationMetricsCalculator)->calculate([], []);

        $this->assertNull($metrics['accuracy']);
        $this->assertNull($metrics['coverage']);
        $this->assertNull($metrics['latency']['mean_ms']);
        $this->assertSame([null, null], $metrics['wilson_95_ci']);
    }
}
