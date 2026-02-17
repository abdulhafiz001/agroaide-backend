<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
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
            ]);

        $dayTasks = $tasks->filter(fn ($t) => $t['scheduledDate'] === $selectedDate)->values();

        return response()->json([
            'tasks' => $tasks,
            'dayPlan' => $dayTasks,
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
        ]);

        $task = CalendarTask::create([
            'user_id' => $request->user()->id,
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
}
