<?php

namespace Tests\Feature;

use App\Models\FarmImageAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanHistoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_history_requires_auth(): void
    {
        $this->getJson('/api/farm/scan-history')->assertUnauthorized();
    }

    public function test_scan_history_lists_only_current_user_scans(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        FarmImageAnalysis::create([
            'user_id' => $user->id,
            'condition' => 'diseased',
            'disease_name' => 'Late Blight',
            'latitude' => 9.05,
            'longitude' => 7.49,
            'result_json' => [
                'condition' => 'diseased',
                'conditionLabel' => 'Diseased',
                'summary' => 'Leaf blight suspected',
                'confidencePercent' => 82,
            ],
        ]);

        FarmImageAnalysis::create([
            'user_id' => $other->id,
            'condition' => 'healthy',
            'disease_name' => null,
            'result_json' => ['condition' => 'healthy', 'summary' => 'Other user scan'],
        ]);

        $token = $user->createToken('mobile-app')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/farm/scan-history');

        $response->assertOk()
            ->assertJsonCount(1, 'history')
            ->assertJsonPath('history.0.diseaseName', 'Late Blight');
    }

    public function test_scan_detail_returns_analysis_for_owner(): void
    {
        $user = User::factory()->create();
        $scan = FarmImageAnalysis::create([
            'user_id' => $user->id,
            'condition' => 'fair',
            'disease_name' => null,
            'result_json' => [
                'condition' => 'fair',
                'conditionLabel' => 'Fair',
                'summary' => 'Mild stress',
                'confidencePercent' => 70,
                'recommendations' => ['immediate' => [], 'products' => [], 'prevention' => [], 'longTerm' => []],
            ],
        ]);

        $token = $user->createToken('mobile-app')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/farm/scan-history/'.$scan->id)
            ->assertOk()
            ->assertJsonPath('scan.id', (string) $scan->id)
            ->assertJsonPath('scan.analysis.summary', 'Mild stress');
    }

    public function test_scan_detail_hides_other_users_scans(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $scan = FarmImageAnalysis::create([
            'user_id' => $owner->id,
            'condition' => 'healthy',
            'result_json' => ['condition' => 'healthy', 'summary' => 'Private'],
        ]);

        $token = $intruder->createToken('mobile-app')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/farm/scan-history/'.$scan->id)
            ->assertNotFound();
    }
}
