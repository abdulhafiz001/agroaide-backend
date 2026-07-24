<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarTask;
use App\Models\FarmField;
use App\Models\FieldTransaction;
use App\Models\JournalEntry;
use App\Models\SyncActionLog;
use App\Services\GeoAreaService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    public function __construct(private GeoAreaService $geoAreaService) {}

    public function delta(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'actions' => ['required', 'array', 'min:1'],
            'actions.*.uuid' => ['required', 'uuid'],
            'actions.*.clientTimestamp' => ['required', 'date'],
            'actions.*.actionType' => ['required', 'string'],
            'actions.*.payload' => ['nullable', 'array'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $results = [];

        foreach ($validated['actions'] as $action) {
            $results[] = $this->applyAction($user->id, $action);
        }

        return response()->json(['results' => $results]);
    }

    public function pull(Request $request): JsonResponse
    {
        $sinceRaw = $request->query('since');
        $since = $sinceRaw ? Carbon::parse($sinceRaw) : Carbon::createFromTimestamp(0);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $fields = FarmField::where('user_id', $user->id)
            ->where('updated_at', '>', $since)
            ->get()
            ->map(fn (FarmField $f) => [
                'id' => (string) $f->id,
                'clientUuid' => $f->client_uuid,
                'name' => $f->name,
                'crop' => $f->crop,
                'areaM2' => (float) $f->area_m2,
                'boundaryGeojson' => $f->boundary_geojson,
                'boundaryUpdatedAt' => $f->boundary_updated_at?->toIso8601String(),
                'healthPercentage' => $f->health_percentage,
                'moisturePercentage' => $f->moisture_percentage,
                'plantedAt' => $f->planted_at?->toDateString(),
                'status' => $f->status,
                'updatedAt' => $f->updated_at?->toIso8601String(),
            ]);

        $tasks = CalendarTask::where('user_id', $user->id)
            ->where('updated_at', '>', $since)
            ->get()
            ->map(fn (CalendarTask $t) => [
                'id' => (string) $t->id,
                'clientUuid' => $t->client_uuid,
                'title' => $t->title,
                'description' => $t->description,
                'scheduledDate' => $t->scheduled_date?->toDateString(),
                'period' => $t->period,
                'durationMinutes' => $t->duration_minutes,
                'impact' => $t->impact,
                'completed' => $t->completed,
                'completedAt' => $t->completed_at?->toIso8601String(),
                'updatedAt' => $t->updated_at?->toIso8601String(),
            ]);

        $transactions = FieldTransaction::where('user_id', $user->id)
            ->where('updated_at', '>', $since)
            ->get()
            ->map(fn (FieldTransaction $t) => [
                'id' => (string) $t->id,
                'clientUuid' => $t->client_uuid,
                'farmFieldId' => (string) $t->farm_field_id,
                'type' => $t->type,
                'category' => $t->category,
                'amount' => (float) $t->amount,
                'quantity' => $t->quantity !== null ? (float) $t->quantity : null,
                'unit' => $t->unit,
                'occurredOn' => $t->occurred_on?->toDateString(),
                'note' => $t->note,
                'updatedAt' => $t->updated_at?->toIso8601String(),
            ]);

        $journal = JournalEntry::where('user_id', $user->id)
            ->where('updated_at', '>', $since)
            ->get()
            ->map(fn (JournalEntry $e) => [
                'id' => (string) $e->id,
                'clientUuid' => $e->client_uuid,
                'farmFieldId' => $e->farm_field_id ? (string) $e->farm_field_id : null,
                'type' => $e->type,
                'note' => $e->note,
                'updatedAt' => $e->updated_at?->toIso8601String(),
                'createdAt' => $e->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'since' => $since->toIso8601String(),
            'serverTime' => now()->toIso8601String(),
            'fields' => $fields,
            'tasks' => $tasks,
            'transactions' => $transactions,
            'journal' => $journal,
        ]);
    }

    /**
     * @param  array{uuid: string, clientTimestamp: string, actionType: string, payload?: array<string, mixed>}  $action
     * @return array<string, mixed>
     */
    private function applyAction(int $userId, array $action): array
    {
        $uuid = $action['uuid'];
        $actionType = $action['actionType'];
        $payload = $action['payload'] ?? [];
        $clientTs = Carbon::parse($action['clientTimestamp']);

        $existingLog = SyncActionLog::where('uuid', $uuid)->first();
        if ($existingLog) {
            return [
                'uuid' => $uuid,
                'actionType' => $actionType,
                'status' => 'duplicate',
                'message' => 'Action already applied.',
                'entityId' => $existingLog->payload['entityId'] ?? null,
            ];
        }

        try {
            $result = DB::transaction(function () use ($userId, $uuid, $actionType, $payload, $clientTs) {
                $outcome = match ($actionType) {
                    'field.create' => $this->fieldCreate($userId, $payload, $clientTs),
                    'field.update' => $this->fieldUpdate($userId, $payload, $clientTs),
                    'field.delete' => $this->fieldDelete($userId, $payload, $clientTs),
                    'journal.create' => $this->journalCreate($userId, $payload),
                    'task.create' => $this->taskCreate($userId, $payload),
                    'task.update' => $this->taskUpdate($userId, $payload, $clientTs),
                    'task.complete' => $this->taskComplete($userId, $payload, $clientTs),
                    'task.delete' => $this->taskDelete($userId, $payload, $clientTs),
                    'transaction.create' => $this->transactionCreate($userId, $payload),
                    'transaction.update' => $this->transactionUpdate($userId, $payload, $clientTs),
                    'transaction.delete' => $this->transactionDelete($userId, $payload, $clientTs),
                    'boundary.update' => $this->boundaryUpdate($userId, $payload, $clientTs),
                    default => [
                        'status' => 'rejected',
                        'message' => "Unknown actionType: {$actionType}",
                    ],
                };

                SyncActionLog::create([
                    'user_id' => $userId,
                    'uuid' => $uuid,
                    'action_type' => $actionType,
                    'payload' => array_merge($payload, [
                        'entityId' => $outcome['entityId'] ?? null,
                        'resultStatus' => $outcome['status'] ?? 'applied',
                    ]),
                    'status' => $outcome['status'] ?? 'applied',
                    'applied_at' => now(),
                ]);

                return $outcome;
            });

            return array_merge([
                'uuid' => $uuid,
                'actionType' => $actionType,
            ], $result);
        } catch (\Throwable $e) {
            return [
                'uuid' => $uuid,
                'actionType' => $actionType,
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function fieldCreate(int $userId, array $payload, Carbon $clientTs): array
    {
        $clientUuid = $payload['clientUuid'] ?? $payload['uuid'] ?? null;
        if ($clientUuid) {
            $existing = FarmField::where('user_id', $userId)->where('client_uuid', $clientUuid)->first();
            if ($existing) {
                return ['status' => 'duplicate', 'entityId' => (string) $existing->id, 'message' => 'Field already exists.'];
            }
        }

        $field = FarmField::create([
            'user_id' => $userId,
            'client_uuid' => $clientUuid,
            'name' => $payload['name'] ?? 'Untitled Field',
            'crop' => $payload['crop'] ?? 'Unknown',
            'area_m2' => $payload['areaM2'] ?? 0,
            'planted_at' => $payload['plantedAt'] ?? null,
            'status' => $payload['status'] ?? 'active',
        ]);

        return ['status' => 'applied', 'entityId' => (string) $field->id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function fieldUpdate(int $userId, array $payload, Carbon $clientTs): array
    {
        $field = $this->findField($userId, $payload);
        if (! $field) {
            return ['status' => 'rejected', 'message' => 'Field not found.'];
        }

        if ($this->isConflict($field->updated_at, $clientTs)) {
            return [
                'status' => 'conflict',
                'entityId' => (string) $field->id,
                'message' => 'Server version is newer (LWW).',
                'serverUpdatedAt' => $field->updated_at?->toIso8601String(),
            ];
        }

        $update = [];
        foreach (['name' => 'name', 'crop' => 'crop', 'areaM2' => 'area_m2', 'status' => 'status', 'plantedAt' => 'planted_at', 'healthPercentage' => 'health_percentage', 'moisturePercentage' => 'moisture_percentage'] as $from => $to) {
            if (array_key_exists($from, $payload)) {
                $update[$to] = $payload[$from];
            }
        }
        $field->update($update);

        return ['status' => 'applied', 'entityId' => (string) $field->id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function fieldDelete(int $userId, array $payload, Carbon $clientTs): array
    {
        $field = $this->findField($userId, $payload);
        if (! $field) {
            return ['status' => 'duplicate', 'message' => 'Field already deleted.'];
        }

        if ($this->isConflict($field->updated_at, $clientTs)) {
            return [
                'status' => 'conflict',
                'entityId' => (string) $field->id,
                'message' => 'Server version is newer (LWW).',
            ];
        }

        $id = (string) $field->id;
        $field->delete();

        return ['status' => 'applied', 'entityId' => $id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function journalCreate(int $userId, array $payload): array
    {
        $clientUuid = $payload['clientUuid'] ?? null;
        if ($clientUuid) {
            $existing = JournalEntry::where('user_id', $userId)->where('client_uuid', $clientUuid)->first();
            if ($existing) {
                return ['status' => 'duplicate', 'entityId' => (string) $existing->id];
            }
        }

        $entry = JournalEntry::create([
            'user_id' => $userId,
            'client_uuid' => $clientUuid,
            'farm_field_id' => $payload['farmFieldId'] ?? null,
            'type' => $payload['type'] ?? 'observation',
            'note' => $payload['note'] ?? '',
        ]);

        return ['status' => 'applied', 'entityId' => (string) $entry->id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function taskCreate(int $userId, array $payload): array
    {
        $clientUuid = $payload['clientUuid'] ?? null;
        if ($clientUuid) {
            $existing = CalendarTask::where('user_id', $userId)->where('client_uuid', $clientUuid)->first();
            if ($existing) {
                return ['status' => 'duplicate', 'entityId' => (string) $existing->id];
            }
        }

        $task = CalendarTask::create([
            'user_id' => $userId,
            'client_uuid' => $clientUuid,
            'title' => $payload['title'] ?? 'Task',
            'description' => $payload['description'] ?? null,
            'scheduled_date' => $payload['scheduledDate'] ?? now()->toDateString(),
            'period' => $payload['period'] ?? 'morning',
            'duration_minutes' => $payload['durationMinutes'] ?? 30,
            'impact' => $payload['impact'] ?? 'medium',
        ]);

        return ['status' => 'applied', 'entityId' => (string) $task->id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function taskUpdate(int $userId, array $payload, Carbon $clientTs): array
    {
        $task = $this->findTask($userId, $payload);
        if (! $task) {
            return ['status' => 'rejected', 'message' => 'Task not found.'];
        }
        if ($this->isConflict($task->updated_at, $clientTs)) {
            return ['status' => 'conflict', 'entityId' => (string) $task->id, 'message' => 'Server version is newer (LWW).'];
        }

        $update = [];
        foreach (['title' => 'title', 'description' => 'description', 'scheduledDate' => 'scheduled_date', 'period' => 'period', 'durationMinutes' => 'duration_minutes', 'impact' => 'impact'] as $from => $to) {
            if (array_key_exists($from, $payload)) {
                $update[$to] = $payload[$from];
            }
        }
        $task->update($update);

        return ['status' => 'applied', 'entityId' => (string) $task->id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function taskComplete(int $userId, array $payload, Carbon $clientTs): array
    {
        $task = $this->findTask($userId, $payload);
        if (! $task) {
            return ['status' => 'rejected', 'message' => 'Task not found.'];
        }
        if ($this->isConflict($task->updated_at, $clientTs)) {
            return ['status' => 'conflict', 'entityId' => (string) $task->id, 'message' => 'Server version is newer (LWW).'];
        }

        $completed = array_key_exists('completed', $payload) ? (bool) $payload['completed'] : true;
        $task->update([
            'completed' => $completed,
            'completed_at' => $completed ? now() : null,
        ]);

        return ['status' => 'applied', 'entityId' => (string) $task->id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function taskDelete(int $userId, array $payload, Carbon $clientTs): array
    {
        $task = $this->findTask($userId, $payload);
        if (! $task) {
            return ['status' => 'duplicate', 'message' => 'Task already deleted.'];
        }
        if ($this->isConflict($task->updated_at, $clientTs)) {
            return ['status' => 'conflict', 'entityId' => (string) $task->id, 'message' => 'Server version is newer (LWW).'];
        }

        $id = (string) $task->id;
        $task->delete();

        return ['status' => 'applied', 'entityId' => $id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function transactionCreate(int $userId, array $payload): array
    {
        $clientUuid = $payload['clientUuid'] ?? null;
        if ($clientUuid) {
            $existing = FieldTransaction::where('user_id', $userId)->where('client_uuid', $clientUuid)->first();
            if ($existing) {
                return ['status' => 'duplicate', 'entityId' => (string) $existing->id];
            }
        }

        $fieldId = $payload['farmFieldId'] ?? $payload['fieldId'] ?? null;
        if (! $fieldId || ! FarmField::where('user_id', $userId)->where('id', $fieldId)->exists()) {
            return ['status' => 'rejected', 'message' => 'Valid farmFieldId required.'];
        }

        $tx = FieldTransaction::create([
            'user_id' => $userId,
            'farm_field_id' => $fieldId,
            'client_uuid' => $clientUuid,
            'type' => $payload['type'] ?? 'EXPENSE',
            'category' => $payload['category'] ?? 'OTHER',
            'amount' => $payload['amount'] ?? 0,
            'quantity' => $payload['quantity'] ?? null,
            'unit' => $payload['unit'] ?? null,
            'occurred_on' => $payload['occurredOn'] ?? now()->toDateString(),
            'note' => $payload['note'] ?? null,
        ]);

        return ['status' => 'applied', 'entityId' => (string) $tx->id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function transactionUpdate(int $userId, array $payload, Carbon $clientTs): array
    {
        $tx = $this->findTransaction($userId, $payload);
        if (! $tx) {
            return ['status' => 'rejected', 'message' => 'Transaction not found.'];
        }
        if ($this->isConflict($tx->updated_at, $clientTs)) {
            return ['status' => 'conflict', 'entityId' => (string) $tx->id, 'message' => 'Server version is newer (LWW).'];
        }

        $update = [];
        foreach (['type' => 'type', 'category' => 'category', 'amount' => 'amount', 'quantity' => 'quantity', 'unit' => 'unit', 'occurredOn' => 'occurred_on', 'note' => 'note'] as $from => $to) {
            if (array_key_exists($from, $payload)) {
                $update[$to] = $payload[$from];
            }
        }
        $tx->update($update);

        return ['status' => 'applied', 'entityId' => (string) $tx->id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function transactionDelete(int $userId, array $payload, Carbon $clientTs): array
    {
        $tx = $this->findTransaction($userId, $payload);
        if (! $tx) {
            return ['status' => 'duplicate', 'message' => 'Transaction already deleted.'];
        }
        if ($this->isConflict($tx->updated_at, $clientTs)) {
            return ['status' => 'conflict', 'entityId' => (string) $tx->id, 'message' => 'Server version is newer (LWW).'];
        }

        $id = (string) $tx->id;
        $tx->delete();

        return ['status' => 'applied', 'entityId' => $id];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function boundaryUpdate(int $userId, array $payload, Carbon $clientTs): array
    {
        $field = $this->findField($userId, $payload);
        if (! $field) {
            return ['status' => 'rejected', 'message' => 'Field not found.'];
        }

        $compareAt = $field->boundary_updated_at ?? $field->updated_at;
        if ($this->isConflict($compareAt, $clientTs)) {
            return [
                'status' => 'conflict',
                'entityId' => (string) $field->id,
                'message' => 'Server boundary is newer (LWW).',
            ];
        }

        $geojson = $payload['boundaryGeojson'] ?? $payload['geojson'] ?? null;
        if (! is_array($geojson) || ($geojson['type'] ?? null) !== 'Polygon') {
            return ['status' => 'rejected', 'message' => 'Valid Polygon geojson required.'];
        }

        try {
            $serverArea = $this->geoAreaService->areaFromGeoJsonPolygon($geojson);
        } catch (\InvalidArgumentException $e) {
            return ['status' => 'rejected', 'message' => $e->getMessage()];
        }

        if (isset($payload['areaM2'])) {
            if (! $this->geoAreaService->validateClientArea((float) $payload['areaM2'], $serverArea, 0.1)) {
                return [
                    'status' => 'rejected',
                    'message' => 'Client area differs from server-computed area by more than 10%.',
                    'serverAreaM2' => round($serverArea, 2),
                ];
            }
        }

        $field->update([
            'boundary_geojson' => $geojson,
            'area_m2' => round($serverArea, 2),
            'boundary_updated_at' => now(),
            'client_uuid' => $payload['clientUuid'] ?? $field->client_uuid,
        ]);

        return [
            'status' => 'applied',
            'entityId' => (string) $field->id,
            'areaM2' => round($serverArea, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findField(int $userId, array $payload): ?FarmField
    {
        $query = FarmField::where('user_id', $userId);
        if (! empty($payload['id'])) {
            return $query->where('id', $payload['id'])->first();
        }
        if (! empty($payload['fieldId'])) {
            return $query->where('id', $payload['fieldId'])->first();
        }
        if (! empty($payload['clientUuid'])) {
            return $query->where('client_uuid', $payload['clientUuid'])->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findTask(int $userId, array $payload): ?CalendarTask
    {
        $query = CalendarTask::where('user_id', $userId);
        if (! empty($payload['id'])) {
            return $query->where('id', $payload['id'])->first();
        }
        if (! empty($payload['taskId'])) {
            return $query->where('id', $payload['taskId'])->first();
        }
        if (! empty($payload['clientUuid'])) {
            return $query->where('client_uuid', $payload['clientUuid'])->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findTransaction(int $userId, array $payload): ?FieldTransaction
    {
        $query = FieldTransaction::where('user_id', $userId);
        if (! empty($payload['id'])) {
            return $query->where('id', $payload['id'])->first();
        }
        if (! empty($payload['transactionId'])) {
            return $query->where('id', $payload['transactionId'])->first();
        }
        if (! empty($payload['clientUuid'])) {
            return $query->where('client_uuid', $payload['clientUuid'])->first();
        }

        return null;
    }

    private function isConflict(?CarbonInterface $serverUpdatedAt, Carbon $clientTs): bool
    {
        if (! $serverUpdatedAt) {
            return false;
        }

        return $serverUpdatedAt->gt($clientTs);
    }
}
