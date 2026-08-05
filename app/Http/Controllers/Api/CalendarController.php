<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarTask;
use App\Models\CropWatch;
use App\Models\PlantingReminder;
use App\Services\SeasonalCalendarService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function __construct(private SeasonalCalendarService $seasonalCalendar) {}

    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $selectedDate = $request->query('date', now()->toDateString());

        $tasks = $user->calendarTasks()
            ->orderBy('scheduled_date')
            ->orderByRaw("FIELD(period, 'morning', 'afternoon', 'evening')")
            ->get()
            ->map(fn (CalendarTask $t) => [
                'id' => (string) $t->id,
                'title' => $t->title,
                'description' => $t->description,
                'scheduledDate' => $t->scheduled_date->toDateString(),
                'period' => $t->period,
                'durationMinutes' => $t->duration_minutes,
                'impact' => $t->impact,
                'completed' => $t->completed,
                'completedAt' => $t->completed_at?->toIso8601String(),
            ]);

        $markedDates = $user->calendarTasks()
            ->selectRaw('scheduled_date, COUNT(*) as task_count, SUM(completed) as done_count')
            ->groupBy('scheduled_date')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->scheduled_date->toDateString() => [
                    'marked' => true,
                    'dotColor' => $row->done_count >= $row->task_count ? '#2eb873' : '#db9534',
                ],
            ])
            ->all();

        $reminders = PlantingReminder::where('user_id', $user->id)
            ->whereDate('plant_on', '>=', now()->subDays(1))
            ->orderBy('plant_on')
            ->get();

        foreach ($reminders as $reminder) {
            $date = $reminder->plant_on->toDateString();
            $markedDates[$date] = [
                'marked' => true,
                'dotColor' => '#3b82f6',
                'plantingReminder' => true,
            ];
        }

        // Analyzed crop watches → mark best plant dates on the calendar (green).
        $watches = CropWatch::where('user_id', $user->id)
            ->whereNotNull('best_plant_date')
            ->whereIn('last_analysis_status', ['window_open', 'waiting'])
            ->get();

        foreach ($watches as $watch) {
            $date = $watch->best_plant_date?->toDateString();
            if (! $date) {
                continue;
            }
            $markedDates[$date] = [
                'marked' => true,
                'dotColor' => '#166534',
                'cropWatch' => true,
                'crop' => $watch->crop,
            ];
        }

        $dayTasks = $tasks->filter(fn ($t) => $t['scheduledDate'] === $selectedDate)->values();
        $dayReminders = $reminders
            ->filter(fn (PlantingReminder $r) => $r->plant_on->toDateString() === $selectedDate)
            ->values()
            ->map(fn (PlantingReminder $r) => [
                'id' => (string) $r->id,
                'crop' => $r->crop,
                'plantOn' => $r->plant_on->toDateString(),
                'kind' => 'planting_reminder',
                'title' => "Plant {$r->crop}",
                'description' => 'Planting reminder set from crop watch.',
            ]);

        $dayWatchPlantings = $watches
            ->filter(fn (CropWatch $w) => $w->best_plant_date?->toDateString() === $selectedDate)
            ->values()
            ->map(fn (CropWatch $w) => [
                'id' => 'watch-'.$w->id,
                'crop' => $w->crop,
                'plantOn' => $w->best_plant_date?->toDateString(),
                'kind' => 'crop_watch_plant',
                'title' => "Plant {$w->crop}",
                'description' => 'Suggested planting date from your crop watch analysis.',
                'status' => $w->last_analysis_status,
            ]);

        return response()->json([
            'tasks' => $tasks,
            'dayPlan' => $dayTasks,
            'dayReminders' => $dayReminders->concat($dayWatchPlantings)->values(),
            'markedDates' => $markedDates,
            'selectedDate' => $selectedDate,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'scheduledDate' => ['required', 'date'],
            'period' => ['nullable', 'in:morning,afternoon,evening'],
            'durationMinutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'impact' => ['nullable', 'in:low,medium,high'],
            'clientUuid' => ['nullable', 'uuid'],
        ]);

        if (! empty($validated['clientUuid'])) {
            $existing = CalendarTask::where('user_id', $request->user()->id)
                ->where('client_uuid', $validated['clientUuid'])
                ->first();
            if ($existing) {
                return response()->json([
                    'task' => [
                        'id' => (string) $existing->id,
                        'title' => $existing->title,
                        'description' => $existing->description,
                        'scheduledDate' => $existing->scheduled_date->toDateString(),
                        'period' => $existing->period,
                        'durationMinutes' => $existing->duration_minutes,
                        'impact' => $existing->impact,
                        'completed' => $existing->completed,
                        'clientUuid' => $existing->client_uuid,
                    ],
                    'idempotent' => true,
                ]);
            }
        }

        $task = CalendarTask::create([
            'user_id' => $request->user()->id,
            'client_uuid' => $validated['clientUuid'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'scheduled_date' => $validated['scheduledDate'],
            'period' => $validated['period'] ?? 'morning',
            'duration_minutes' => $validated['durationMinutes'] ?? 30,
            'impact' => $validated['impact'] ?? 'medium',
        ]);

        return response()->json([
            'task' => [
                'id' => (string) $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'scheduledDate' => $task->scheduled_date->toDateString(),
                'period' => $task->period,
                'durationMinutes' => $task->duration_minutes,
                'impact' => $task->impact,
                'completed' => $task->completed,
                'clientUuid' => $task->client_uuid,
            ],
        ], 201);
    }

    public function update(Request $request, int $taskId): JsonResponse
    {
        $task = CalendarTask::where('user_id', $request->user()->id)
            ->where('id', $taskId)
            ->firstOrFail();

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'scheduledDate' => ['nullable', 'date'],
            'period' => ['nullable', 'in:morning,afternoon,evening'],
            'durationMinutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'impact' => ['nullable', 'in:low,medium,high'],
        ]);

        $updateData = [];
        if (isset($validated['title'])) $updateData['title'] = $validated['title'];
        if (isset($validated['description'])) $updateData['description'] = $validated['description'];
        if (isset($validated['scheduledDate'])) $updateData['scheduled_date'] = $validated['scheduledDate'];
        if (isset($validated['period'])) $updateData['period'] = $validated['period'];
        if (isset($validated['durationMinutes'])) $updateData['duration_minutes'] = $validated['durationMinutes'];
        if (isset($validated['impact'])) $updateData['impact'] = $validated['impact'];

        $task->update($updateData);

        return response()->json(['message' => 'Task updated.']);
    }

    public function destroy(Request $request, int $taskId): JsonResponse
    {
        CalendarTask::where('user_id', $request->user()->id)
            ->where('id', $taskId)
            ->firstOrFail()
            ->delete();

        return response()->json(['message' => 'Task deleted.']);
    }

    public function completeTask(Request $request, int $taskId): JsonResponse
    {
        $task = CalendarTask::where('user_id', $request->user()->id)
            ->where('id', $taskId)
            ->firstOrFail();

        $completed = $request->input('completed', true);
        $task->update([
            'completed' => $completed,
            'completed_at' => $completed ? now() : null,
        ]);

        return response()->json([
            'taskId' => (string) $task->id,
            'completed' => $task->completed,
            'message' => $completed ? 'Task marked as complete.' : 'Task unmarked.',
        ]);
    }

    public function seasonalSuggestions(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $crop = $request->query('crop');
        $fieldId = $request->query('fieldId');

        $result = $this->seasonalCalendar->suggestionsForUser(
            $user,
            $crop ? (string) $crop : null,
            $fieldId !== null ? (int) $fieldId : null,
        );

        return response()->json($result);
    }

    public function listCropWatches(Request $request): JsonResponse
    {
        $watches = CropWatch::where('user_id', $request->user()->id)
            ->orderBy('crop')
            ->get()
            ->map(fn (CropWatch $w) => $this->serializeWatch($w));

        return response()->json(['watches' => $watches]);
    }

    public function storeCropWatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'crop' => ['required', 'string', 'max:100'],
            'notifyWhenPlantingWindow' => ['nullable', 'boolean'],
        ]);

        $crop = $this->seasonalCalendar->normalizeCropName($validated['crop']);

        $watch = CropWatch::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'crop' => $crop,
            ],
            [
                'notify_when_planting_window' => $validated['notifyWhenPlantingWindow'] ?? true,
            ],
        );

        return response()->json([
            'watch' => $this->serializeWatch($watch),
        ], $watch->wasRecentlyCreated ? 201 : 200);
    }

    public function destroyCropWatch(Request $request, int $id): JsonResponse
    {
        CropWatch::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail()
            ->delete();

        return response()->json(['message' => 'Crop watch removed.']);
    }

    public function setPlantingReminder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notificationId' => ['nullable', 'integer'],
            'watchId' => ['nullable', 'integer'],
            'crop' => ['required', 'string', 'max:100'],
            'plantOn' => ['required', 'date'],
        ]);

        $plantOn = Carbon::parse($validated['plantOn'])->startOfDay();
        $remind2d = $plantOn->copy()->subDays(2)->setTime(8, 0);
        $remindOn = $plantOn->copy()->setTime(7, 0);

        $watchId = $validated['watchId'] ?? null;
        if ($watchId) {
            $owns = CropWatch::where('user_id', $request->user()->id)->where('id', $watchId)->exists();
            if (! $owns) {
                $watchId = null;
            }
        }

        $reminder = PlantingReminder::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'crop' => $validated['crop'],
                'plant_on' => $plantOn->toDateString(),
            ],
            [
                'crop_watch_id' => $watchId,
                'notification_id' => $validated['notificationId'] ?? null,
                'remind_2d_at' => $remind2d,
                'remind_on_at' => $remindOn,
                'local_scheduled' => false,
            ],
        );

        return response()->json([
            'reminder' => [
                'id' => (string) $reminder->id,
                'crop' => $reminder->crop,
                'plantOn' => $reminder->plant_on->toDateString(),
                'remind2dAt' => $reminder->remind_2d_at->toIso8601String(),
                'remindOnAt' => $reminder->remind_on_at->toIso8601String(),
            ],
            'localSchedule' => [
                [
                    'id' => 'planting-2d-'.$reminder->id,
                    'title' => "Plant {$reminder->crop} in 2 days",
                    'body' => "Prepare for planting {$reminder->crop} on {$reminder->plant_on->toDateString()}.",
                    'triggerAt' => $reminder->remind_2d_at->toIso8601String(),
                ],
                [
                    'id' => 'planting-day-'.$reminder->id,
                    'title' => "Plant {$reminder->crop} today",
                    'body' => "Today is planting day for {$reminder->crop}.",
                    'triggerAt' => $reminder->remind_on_at->toIso8601String(),
                ],
            ],
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeWatch(CropWatch $w): array
    {
        return [
            'id' => (string) $w->id,
            'crop' => $w->crop,
            'notifyWhenPlantingWindow' => (bool) $w->notify_when_planting_window,
            'status' => $w->status ?? 'active',
            'bestPlantDate' => $w->best_plant_date?->toDateString(),
            'lastAnalysisStatus' => $w->last_analysis_status,
            'lastNotifiedOn' => $w->last_notified_on?->toDateString(),
            'createdAt' => $w->created_at?->toIso8601String(),
        ];
    }
}
