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
        $field->planted_at = Carbon::parse($plantedOn)->toDateString();
        $field->planted_at_recorded_at = now();
        $field->harvest_estimate_notified_at = null;
        $field->harvest_reminder_sent_at = null;

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
     * Create/update soft calendar tasks across the harvest window.
     */
    public function syncCalendarHarvestTasks(FarmField $field): void
    {
        if (! $field->harvest_start_date || ! $field->harvest_end_date || ! $field->user_id) {
            return;
        }

        $start = Carbon::parse($field->harvest_start_date)->startOfDay();
        $end = Carbon::parse($field->harvest_end_date)->startOfDay();
        $title = "Harvest window: {$field->crop}".($field->name ? " ({$field->name})" : '');
        $marker = "[harvest-window:fieldId={$field->id}]";

        // Remove previous harvest-window tasks for this field
        CalendarTask::where('user_id', $field->user_id)
            ->where('description', 'like', "%{$marker}%")
            ->delete();

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            CalendarTask::create([
                'user_id' => $field->user_id,
                'title' => $title,
                'description' => "AI estimate based on your planting date. Best days to harvest this crop — not a single-day deadline. {$marker}",
                'scheduled_date' => $day->toDateString(),
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
            $title = "Harvest estimate: {$field->crop}";
            $message = "Based on when you planted {$field->crop}".($field->name ? " in {$field->name}" : '')
                .", a good harvest window is about {$startLabel} to {$endLabel} (~{$window['offsetDays']} days after planting). "
                .'We added those days to your calendar.';

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
     * Day before harvest window starts — remind farmer.
     */
    public function sendDueHarvestReminders(): int
    {
        $sent = 0;
        $tomorrow = now()->addDay()->toDateString();

        $fields = FarmField::query()
            ->whereDate('harvest_start_date', $tomorrow)
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

            $title = "Harvest soon: {$field->crop}";
            $message = "From tomorrow it is okay to start harvesting {$field->crop}"
                .($field->name ? " ({$field->name})" : '')
                .". Based on the planting date you entered, the estimated window is {$startLabel} to {$endLabel}. "
                .'Open for advice from your personalized AI advisor.';

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
            ->whereNull('planted_at')
            ->where('status', '!=', 'archived')
            ->orderBy('name')
            ->get(['id', 'name', 'crop'])
            ->map(fn (FarmField $f) => [
                'id' => (string) $f->id,
                'name' => $f->name,
                'crop' => $f->crop,
            ])
            ->values()
            ->all();
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
