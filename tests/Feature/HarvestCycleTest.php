<?php

namespace Tests\Feature;

use App\Models\CalendarTask;
use App\Models\FarmField;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\HarvestEstimateService;
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

    public function test_harvest_notifications_and_tasks_are_clean_and_free_of_em_dashes_or_markers(): void
    {
        $user = $this->farmer();
        $service = app(HarvestEstimateService::class);

        $field = FarmField::create([
            'user_id' => $user->id,
            'name' => 'Plot 5',
            'crop' => 'maize',
            'area_m2' => 1000,
            'status' => 'active',
            'planted_at' => now()->subDays(99)->toDateString(),
            'planted_at_recorded_at' => now()->subHours(6),
        ]);

        // Trigger estimate notification
        $sentEstimates = $service->sendDueEstimateNotifications();
        $this->assertSame(1, $sentEstimates);

        $estimateNotification = $user->appNotifications()->where('type', 'harvest_estimate')->first();
        $this->assertNotNull($estimateNotification);
        $this->assertStringNotContainsString('—', $estimateNotification->message);
        $this->assertStringNotContainsString('harvest-window:field', $estimateNotification->message);
        $this->assertStringNotContainsString('fieldId=', $estimateNotification->message);
        $this->assertStringContainsString('Plot 5', $estimateNotification->message);

        // Check synced calendar tasks
        $task = CalendarTask::where('user_id', $user->id)->first();
        $this->assertNotNull($task);
        $this->assertStringNotContainsString('—', $task->description);
        $this->assertStringNotContainsString('harvest-window:field', $task->description);
        $this->assertStringNotContainsString('fieldId=', $task->description);

        // Set harvest start date to tomorrow and trigger reminder
        $field->update([
            'harvest_start_date' => now()->addDay()->toDateString(),
            'harvest_end_date' => now()->addDays(5)->toDateString(),
            'harvest_reminder_sent_at' => null,
        ]);

        $sentReminders = $service->sendDueHarvestReminders();
        $this->assertSame(1, $sentReminders);

        $reminderNotification = $user->appNotifications()->where('type', 'harvest_reminder')->first();
        $this->assertNotNull($reminderNotification);
        $this->assertStringNotContainsString('—', $reminderNotification->message);
        $this->assertStringNotContainsString('harvest-window:field', $reminderNotification->message);
        $this->assertStringNotContainsString('fieldId=', $reminderNotification->message);
    }
}
