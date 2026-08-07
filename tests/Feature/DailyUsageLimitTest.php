<?php

namespace Tests\Feature;

use App\Models\AdvisorConversation;
use App\Models\FarmImageAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DailyUsageLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_is_blocked_after_four_scans_in_a_calendar_day(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        foreach (range(1, 4) as $i) {
            FarmImageAnalysis::create([
                'user_id' => $user->id,
                'condition' => 'pending',
                'processing_state' => 'queued',
                'result_json' => [],
            ]);
        }

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/farm/analyze-image', [
            'imageBase64' => 'data:image/png;base64,'.base64_encode(
                hex2bin('89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000d4944415408d763f8cfc0f01f00050001ff89993d1d0000000049454e44ae426082')
            ),
        ]);

        $response->assertTooManyRequests()
            ->assertJsonPath('code', 'daily_scan_limit')
            ->assertJsonPath('limit', 4)
            ->assertJsonPath('used', 4);

        $this->assertDatabaseCount('farm_image_analyses', 4);
    }

    public function test_chat_is_blocked_after_eight_user_messages_in_a_calendar_day(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 8) as $i) {
            AdvisorConversation::create([
                'user_id' => $user->id,
                'role' => 'user',
                'message' => "Question {$i}",
            ]);
        }

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/advisor/chat', [
            'message' => 'One more question',
        ]);

        $response->assertTooManyRequests()
            ->assertJsonPath('code', 'daily_chat_limit')
            ->assertJsonPath('limit', 8)
            ->assertJsonPath('used', 8);

        $this->assertSame(8, AdvisorConversation::where('user_id', $user->id)->where('role', 'user')->count());
    }

    public function test_yesterdays_usage_does_not_count_toward_today(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        foreach (range(1, 4) as $i) {
            $scan = FarmImageAnalysis::create([
                'user_id' => $user->id,
                'condition' => 'pending',
                'processing_state' => 'queued',
                'result_json' => [],
            ]);
            $scan->forceFill(['created_at' => now()->subDay()])->save();
        }

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/farm/analyze-image', [
            'imageBase64' => 'data:image/png;base64,'.base64_encode(
                hex2bin('89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000d4944415408d763f8cfc0f01f00050001ff89993d1d0000000049454e44ae426082')
            ),
        ]);

        $response->assertAccepted();
        $this->assertDatabaseCount('farm_image_analyses', 5);
    }
}
