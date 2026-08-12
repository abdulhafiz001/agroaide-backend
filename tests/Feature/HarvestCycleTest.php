<?php

namespace Tests\Feature;

use App\Models\FarmField;
use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HarvestCycleTest extends TestCase
{
    use RefreshDatabase;

    private function farmer(): User
    {
        $user = User::factory()->create([
            'role' => 'farmer',
            'app_rating_prompt_status' => 'pending',
        ]);
        UserConsent::create([
            'user_id' => $user->id,
            'terms_version' => config('legal.terms.version'),
            'privacy_version' => config('legal.privacy.version'),
            'research_consent' => true,
            'consented_at' => now(),
        ]);

        return $user;
    }

    public function test_mark_harvested_and_plan_next_crop(): void
    {
        $user = $this->farmer();
        Sanctum::actingAs($user);

        $field = FarmField::create([
            'user_id' => $user->id,
            'name' => 'South side',
            'crop' => 'potatoes',
            'area_m2' => 400,
            'status' => 'active',
            'planted_at' => now()->subMonths(3)->toDateString(),
            'harvest_start_date' => now()->subDay()->toDateString(),
            'harvest_end_date' => now()->addDays(3)->toDateString(),
        ]);

        $harvest = $this->postJson("/api/farm/fields/{$field->id}/harvest", [
            'harvestedAt' => now()->toDateString(),
            'yieldNote' => 'about 4 bags',
            'plannedNextCrop' => 'maize',
            'plannedPlantAt' => now()->addDays(20)->toDateString(),
        ]);

        $harvest->assertOk()
            ->assertJsonPath('field.status', 'fallow')
            ->assertJsonPath('field.plannedNextCrop', 'maize')
            ->assertJsonPath('shouldPromptRating', true);

        $this->assertDatabaseHas('farm_fields', [
            'id' => $field->id,
            'status' => 'fallow',
            'crop' => 'potatoes',
            'yield_note' => 'about 4 bags',
            'planned_next_crop' => 'maize',
        ]);

        $rate = $this->postJson('/api/app/ratings', [
            'stars' => 5,
            'source' => 'post_harvest',
        ]);
        $rate->assertOk();
        $this->assertDatabaseHas('app_ratings', ['user_id' => $user->id, 'stars' => 5]);
        $this->assertSame('completed', $user->fresh()->app_rating_prompt_status);
    }
}
