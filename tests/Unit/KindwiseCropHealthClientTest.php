<?php

namespace Tests\Unit;

use App\Services\KindwiseCropHealthClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KindwiseCropHealthClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.kindwise.api_key', 'test-api-key');
        Config::set('services.kindwise.base_url', 'https://crop.kindwise.com/api/v1');
    }

    public function test_classifies_as_healthy_when_is_healthy_binary_is_true_despite_weak_disease_suggestions(): void
    {
        Http::fake([
            'https://crop.kindwise.com/api/v1/*' => Http::response([
                'access_token' => 'token-123',
                'result' => [
                    'is_plant' => ['binary' => true, 'probability' => 0.96],
                    'is_healthy' => ['binary' => true, 'probability' => 0.88],
                    'crop' => [
                        'suggestions' => [
                            ['name' => 'Tomato', 'probability' => 0.91],
                        ],
                    ],
                    'disease' => [
                        'suggestions' => [
                            ['name' => 'Early Blight', 'probability' => 0.42],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $client = new KindwiseCropHealthClient;
        $result = $client->identify('data:image/jpeg;base64,'.base64_encode('sample-image'));

        $this->assertTrue($result['is_crop']);
        $this->assertTrue($result['is_healthy']);
        $this->assertNull($result['disease']);
        $this->assertSame('Tomato', $result['crop']['name'] ?? null);
        $this->assertGreaterThanOrEqual(0.85, $result['confidence']);
    }

    public function test_classifies_as_healthy_when_is_healthy_probability_is_high(): void
    {
        Http::fake([
            'https://crop.kindwise.com/api/v1/*' => Http::response([
                'access_token' => 'token-456',
                'result' => [
                    'is_plant' => ['binary' => true, 'probability' => 0.94],
                    'is_healthy' => ['probability' => 0.75],
                    'crop' => [
                        'suggestions' => [
                            ['name' => 'Maize', 'probability' => 0.89],
                        ],
                    ],
                    'disease' => [
                        'suggestions' => [
                            ['name' => 'Common Rust', 'probability' => 0.48],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $client = new KindwiseCropHealthClient;
        $result = $client->identify('data:image/jpeg;base64,'.base64_encode('sample-image'));

        $this->assertTrue($result['is_crop']);
        $this->assertTrue($result['is_healthy']);
        $this->assertNull($result['disease']);
        $this->assertSame('Maize', $result['crop']['name'] ?? null);
    }

    public function test_classifies_as_diseased_when_is_healthy_is_false_and_disease_probability_is_strong(): void
    {
        Http::fake([
            'https://crop.kindwise.com/api/v1/*' => Http::response([
                'access_token' => 'token-789',
                'result' => [
                    'is_plant' => ['binary' => true, 'probability' => 0.95],
                    'is_healthy' => ['binary' => false, 'probability' => 0.15],
                    'crop' => [
                        'suggestions' => [
                            ['name' => 'Cassava', 'probability' => 0.88],
                        ],
                    ],
                    'disease' => [
                        'suggestions' => [
                            ['name' => 'Cassava Mosaic Disease', 'probability' => 0.82],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $client = new KindwiseCropHealthClient;
        $result = $client->identify('data:image/jpeg;base64,'.base64_encode('sample-image'));

        $this->assertTrue($result['is_crop']);
        $this->assertFalse($result['is_healthy']);
        $this->assertNotNull($result['disease']);
        $this->assertSame('Cassava Mosaic Disease', $result['disease']['name'] ?? null);
        $this->assertEqualsWithDelta(0.88, $result['confidence'], 0.01);
    }

    public function test_classifies_as_healthy_when_no_health_flag_but_disease_probability_is_weak(): void
    {
        Http::fake([
            'https://crop.kindwise.com/api/v1/*' => Http::response([
                'access_token' => 'token-101',
                'result' => [
                    'is_plant' => ['binary' => true, 'probability' => 0.92],
                    'crop' => [
                        'suggestions' => [
                            ['name' => 'Yam', 'probability' => 0.85],
                        ],
                    ],
                    'disease' => [
                        'suggestions' => [
                            ['name' => 'Anthracnose', 'probability' => 0.38],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $client = new KindwiseCropHealthClient;
        $result = $client->identify('data:image/jpeg;base64,'.base64_encode('sample-image'));

        $this->assertTrue($result['is_crop']);
        $this->assertTrue($result['is_healthy']);
        $this->assertNull($result['disease']);
    }
}
