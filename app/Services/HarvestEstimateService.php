<?php

namespace App\Services;

use App\Models\CalendarTask;
use App\Models\FarmField;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class HarvestEstimateService
{
    public function __construct(private NotificationDispatcher $dispatcher) {}

    /**
     * Compute a 5-day harvest window from planted_at + crop stageOffsets.harvest.
     *
     * @return array{start: string, end: string, offsetDays: int}|null
     */
    public function computeWindow(FarmField $field): ?array
    {
        if (! $field->planted_at) {
            return null;
        }

        $cropKey = $this->normalizeCrop((string) $field->crop);
        $offset = (int) config("seasonal_crops.crops.{$cropKey}.stageOffsets.harvest", 90);
        if ($offset < 14) {
            $offset = 90;
        }

        $mid = Carbon::parse($field->planted_at)->startOfDay()->addDays($offset);
        $start = $mid->copy()->subDays(2);
        $end = $mid->copy()->addDays(2);

        return [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'offsetDays' => $offset,
        ];
    }

    public function applyPlantedAt(FarmField $field, string $plantedOn): FarmField
    {
        // If they planned a next crop, adopt it as the active crop when planting starts.
        if (filled($field->planned_next_crop)) {
            $field->crop = (string) $field->planned_next_crop;
        }

        $field->planted_at = Carbon::parse($plantedOn)->toDateString();
        $field->planted_at_recorded_at = now();
        $field->harvest_estimate_notified_at = null;
        $field->harvest_reminder_sent_at = null;
        $field->harvested_at = null;
        $field->yield_note = null;
        $field->planned_next_crop = null;
        $field->planned_plant_at = null;
        $field->next_plant_remind_2d_sent_at = null;
        $field->next_plant_remind_on_sent_at = null;
        $field->status = 'active';

        $window = $this->computeWindow($field);
        if ($window) {
            $field->harvest_start_date = $window['start'];
            $field->harvest_end_date = $window['end'];
        }

        $field->save();
        $this->syncCalendarHarvestTasks($field);

        return $field->fresh();
    }

    /**
     * @param  array{harvestedAt?:string,yieldNote?:?string,plannedNextCrop?:?string,plannedPlantAt?:?string}  $data
     */
    public function markHarvested(FarmField $field, array $data): FarmField
    {
        $harvestedAt = Carbon::parse($data['harvestedAt'] ?? now()->toDateString())->toDateString();

        $field->harvested_at = $harvestedAt;
        $field->yield_note = isset($data['yieldNote']) ? trim((string) $data['yieldNote']) ?: null : $field->yield_note;
        $field->status = 'fallow';
        $field->harvest_start_date = null;
        $field->harvest_end_date = null;
        $field->harvest_estimate_notified_at = $field->harvest_estimate_notified_at ?? now();
        $field->harvest_reminder_sent_at = $field->harvest_reminder_sent_at ?? now();

        if (! empty($data['plannedNextCrop']) && ! empty($data['plannedPlantAt'])) {
            $field->planned_next_crop = trim((string) $data['plannedNextCrop']);
            $field->planned_plant_at = Carbon::parse($data['plannedPlantAt'])->toDateString();
            $field->next_plant_remind_2d_sent_at = null;
            $field->next_plant_remind_on_sent_at = null;
        }

        $field->save();
        $this->clearCalendarHarvestTasks($field);

        return $field->fresh();
    }

    public function planNextCrop(FarmField $field, string $crop, string $plantOn): FarmField
    {
        $field->planned_next_crop = trim($crop);
        $field->planned_plant_at = Carbon::parse($plantOn)->toDateString();
        $field->next_plant_remind_2d_sent_at = null;
        $field->next_plant_remind_on_sent_at = null;
        $field->save();

        return $field->fresh();
    }

    public function clearCalendarHarvestTasks(FarmField $field): void
    {
        if (! $field->user_id) {
            return;
        }

        $marker = "[harvest-window:fieldId={$field->id}]";
        CalendarTask::where('user_id', $field->user_id)
            ->where(function ($q) use ($field, $marker) {
                $q->where('description', 'like', "%{$marker}%")
                    ->orWhere('client_uuid', 'like', "harvest_window_{$field->id}_%");
            })
            ->delete();
    }

    /**
     * Create/update soft calendar tasks across the harvest window.
     */
    public function syncCalendarHarvestTasks(FarmField $field): void
    {
        if (! $field->harvest_start_date || ! $field->harvest_end_date || ! $field->user_id) {
            return;
        }

        $start = Carbon::parse($field->harvest_start_date)->startOfDay();
        $end = Carbon::parse($field->harvest_end_date)->startOfDay();
        $fieldName = trim((string) ($field->name ?: ''));
        $title = "Harvest window: {$field->crop}".($fieldName !== '' ? " ({$fieldName})" : '');

        // Remove previous harvest-window tasks for this field
        $this->clearCalendarHarvestTasks($field);

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $dateStr = $day->toDateString();
            CalendarTask::create([
                'user_id' => $field->user_id,
                'client_uuid' => "harvest_window_{$field->id}_{$dateStr}",
                'title' => $title,
                'description' => "Recommended harvest window based on your planting date. Monitor your field and harvest as your crop reaches peak maturity.",
                'scheduled_date' => $dateStr,
                'period' => 'morning',
                'duration_minutes' => 60,
                'impact' => 'high',
                'completed' => false,
            ]);
        }
    }

    /**
     * After ~5 hours from planting-date entry, notify with harvest estimate.
     */
    public function sendDueEstimateNotifications(): int
    {
        $sent = 0;
        $cutoff = now()->subHours(5);

        $fields = FarmField::query()
            ->whereNotNull('planted_at')
            ->whereNotNull('planted_at_recorded_at')
            ->whereNull('harvested_at')
            ->whereNull('harvest_estimate_notified_at')
            ->where('planted_at_recorded_at', '<=', $cutoff)
            ->with('user')
            ->get();

        foreach ($fields as $field) {
            $user = $field->user;
            if (! $user) {
                continue;
            }

            $window = $this->computeWindow($field);
            if (! $window) {
                continue;
            }

            if (! $field->harvest_start_date) {
                $field->harvest_start_date = $window['start'];
                $field->harvest_end_date = $window['end'];
                $field->save();
                $this->syncCalendarHarvestTasks($field);
            }

            $startLabel = Carbon::parse($window['start'])->format('j M Y');
            $endLabel = Carbon::parse($window['end'])->format('j M Y');
            $fieldName = trim((string) ($field->name ?: ''));
            $title = "Harvest estimate: {$field->crop}";
            $message = "Based on your planting date for {$field->crop}"
                .($fieldName !== '' ? " in {$fieldName}" : '')
                .", your estimated harvest window is between {$startLabel} and {$endLabel} (about {$window['offsetDays']} days after planting). These dates have been added to your calendar.";

            $notification = $this->dispatcher->notify(
                $user,
                'harvest_estimate',
                $title,
                $message,
                [
                    'fieldId' => (string) $field->id,
                    'crop' => $field->crop,
                    'fieldName' => $field->name,
                    'harvestStart' => $window['start'],
                    'harvestEnd' => $window['end'],
                    'plantedAt' => optional($field->planted_at)?->toDateString(),
                    'canSetReminder' => false,
                ],
                ['push' => true, 'dedupeMinutes' => 10080, 'dedupeKey' => 'fieldId'],
            );

            if ($notification) {
                $field->update(['harvest_estimate_notified_at' => now()]);
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Day before harvest window starts - remind farmer.
     */
    public function sendDueHarvestReminders(): int
    {
        $sent = 0;
        $tomorrow = now()->addDay()->toDateString();

        $fields = FarmField::query()
            ->whereDate('harvest_start_date', $tomorrow)
            ->whereNull('harvested_at')
            ->whereNull('harvest_reminder_sent_at')
            ->with('user')
            ->get();

        foreach ($fields as $field) {
            $user = $field->user;
            if (! $user) {
                continue;
            }

            $startLabel = Carbon::parse($field->harvest_start_date)->format('j M Y');
            $endLabel = $field->harvest_end_date
                ? Carbon::parse($field->harvest_end_date)->format('j M Y')
                : $startLabel;
            $fieldName = trim((string) ($field->name ?: ''));

            $title = "Harvest window opening: {$field->crop}";
            $message = "Your {$field->crop}"
                .($fieldName !== '' ? " in {$fieldName}" : '')
                ." enters its recommended harvest window tomorrow, from {$startLabel} to {$endLabel}. Check your field and open AgroAide for harvest tips and market prices.";

            $notification = $this->dispatcher->notify(
                $user,
                'harvest_reminder',
                $title,
                $message,
                [
                    'fieldId' => (string) $field->id,
                    'crop' => $field->crop,
                    'fieldName' => $field->name,
                    'harvestStart' => optional($field->harvest_start_date)?->toDateString(),
                    'harvestEnd' => optional($field->harvest_end_date)?->toDateString(),
                    'plantedAt' => optional($field->planted_at)?->toDateString(),
                    'analysis' => 'harvest_ready',
                ],
                ['push' => true, 'preference' => 'plantingWindowAlerts', 'dedupeMinutes' => 10080, 'dedupeKey' => 'fieldId'],
            );

            if ($notification) {
                $field->update(['harvest_reminder_sent_at' => now()]);
                $sent++;
            } else {
                Log::info('Harvest reminder skipped (pref/dedupe)', ['field_id' => $field->id]);
            }
        }

        return $sent;
    }

    /**
     * Fields missing planted_at for the planting-date prompt.
     *
     * @return list<array{id:string,name:string,crop:string}>
     */
    public function fieldsNeedingPlantDate(User $user): array
    {
        return FarmField::where('user_id', $user->id)
            ->where('status', '!=', 'archived')
            ->where(function ($q) {
                $q->whereNull('planted_at')
                    ->orWhere(function ($q2) {
                        // Fallow + next crop planned: nudge around the plant-by date.
                        $q2->where('status', 'fallow')
                            ->whereNotNull('harvested_at')
                            ->whereNotNull('planned_next_crop')
                            ->whereNotNull('planned_plant_at')
                            ->whereDate('planned_plant_at', '<=', now()->addDays(3)->toDateString());
                    });
            })
            ->orderBy('name')
            ->get(['id', 'name', 'crop', 'planned_next_crop'])
            ->map(fn (FarmField $f) => [
                'id' => (string) $f->id,
                'name' => $f->name,
                'crop' => $f->planned_next_crop ?: $f->crop,
            ])
            ->values()
            ->all();
    }

    /**
     * Notify for planned next plant: 2 days before + planting day.
     */
    public function sendDueNextPlantReminders(): int
    {
        $sent = 0;
        $today = now()->startOfDay();
        $inTwoDays = now()->addDays(2)->toDateString();

        $due2d = FarmField::query()
            ->whereNotNull('planned_next_crop')
            ->whereNotNull('planned_plant_at')
            ->whereNotNull('harvested_at')
            ->whereNull('next_plant_remind_2d_sent_at')
            ->whereDate('planned_plant_at', $inTwoDays)
            ->with('user')
            ->get();

        foreach ($due2d as $field) {
            $user = $field->user;
            if (! $user) {
                continue;
            }
            $fieldName = trim((string) ($field->name ?: ''));
            $dateLabel = Carbon::parse($field->planned_plant_at)->format('j M Y');
            $n = $this->dispatcher->notify(
                $user,
                'next_plant_reminder',
                "Plant {$field->planned_next_crop} in 2 days",
                "Reminder: your scheduled planting day for {$field->planned_next_crop}"
                    .($fieldName !== '' ? " in {$fieldName}" : '')
                    ." is on {$dateLabel}. Prepare your seeds and land.",
                [
                    'fieldId' => (string) $field->id,
                    'crop' => $field->planned_next_crop,
                    'plantOn' => $field->planned_plant_at->toDateString(),
                    'kind' => 'two_days_before',
                ],
                ['push' => true, 'preference' => 'plantingWindowAlerts', 'dedupeMinutes' => 10080, 'dedupeKey' => 'fieldId'],
            );
            if ($n) {
                $field->update(['next_plant_remind_2d_sent_at' => now()]);
                $sent++;
            }
        }

        $dueOn = FarmField::query()
            ->whereNotNull('planned_next_crop')
            ->whereNotNull('planned_plant_at')
            ->whereNotNull('harvested_at')
            ->whereNull('next_plant_remind_on_sent_at')
            ->whereDate('planned_plant_at', '<=', $today->toDateString())
            ->with('user')
            ->get();

        foreach ($dueOn as $field) {
            $user = $field->user;
            if (! $user) {
                continue;
            }
            $fieldName = trim((string) ($field->name ?: ''));
            $n = $this->dispatcher->notify(
                $user,
                'next_plant_reminder',
                "Plant {$field->planned_next_crop} today",
                "Today is your scheduled planting day for {$field->planned_next_crop}"
                    .($fieldName !== '' ? " in {$fieldName}" : '')
                    .". Open AgroAide to record your planting date.",
                [
                    'fieldId' => (string) $field->id,
                    'crop' => $field->planned_next_crop,
                    'plantOn' => $field->planned_plant_at->toDateString(),
                    'kind' => 'planting_day',
                ],
                ['push' => true, 'preference' => 'plantingWindowAlerts', 'dedupeMinutes' => 10080, 'dedupeKey' => 'fieldId'],
            );
            if ($n) {
                $field->update(['next_plant_remind_on_sent_at' => now()]);
                $sent++;
            }
        }

        return $sent;
    }

    private function normalizeCrop(string $crop): string
    {
        $aliases = config('seasonal_crops.aliases', []);
        foreach ($aliases as $alias => $canonical) {
            if (strcasecmp((string) $alias, $crop) === 0) {
                return (string) $canonical;
            }
        }
        foreach (array_keys(config('seasonal_crops.crops', [])) as $name) {
            if (strcasecmp($name, $crop) === 0) {
                return $name;
            }
        }

        return ucwords(strtolower(trim($crop)));
    }
}
