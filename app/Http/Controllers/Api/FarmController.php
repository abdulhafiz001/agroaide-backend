<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FarmField;
use App\Models\JournalEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FarmController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $fields = $user->farmFields()->orderBy('created_at', 'desc')->get()->map(fn (FarmField $f) => [
            'id' => (string) $f->id,
            'name' => $f->name,
            'crop' => $f->crop,
            'area' => $f->area_hectares,
            'health' => $f->health_percentage,
            'moisture' => $f->moisture_percentage,
            'daysSincePlanting' => $f->days_since_planting,
            'status' => $f->status,
            'plantedAt' => $f->planted_at?->toIso8601String(),
        ]);

        $journal = $user->journalEntries()
            ->with('farmField:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(fn (JournalEntry $e) => [
                'id' => (string) $e->id,
                'date' => $e->created_at->toIso8601String(),
                'note' => $e->note,
                'type' => $e->type,
                'fieldName' => $e->farmField?->name,
            ]);

        $lat = $user->farm_latitude ?? 6.8402;
        $lng = $user->farm_longitude ?? 7.3705;

        return response()->json([
            'fields' => $fields,
            'journal' => $journal,
            'map' => [
                'center' => ['latitude' => (float) $lat, 'longitude' => (float) $lng],
                'polygon' => [
                    ['latitude' => $lat + 0.0003, 'longitude' => $lng - 0.0015],
                    ['latitude' => $lat + 0.0006, 'longitude' => $lng + 0.0015],
                    ['latitude' => $lat - 0.0007, 'longitude' => $lng + 0.002],
                    ['latitude' => $lat - 0.001, 'longitude' => $lng - 0.001],
                ],
            ],
            'farmSummary' => [
                'farmName' => $user->farm_name ?? 'My Farm',
                'farmLocation' => $user->farm_location ?? 'Unknown location',
                'farmSizeHectares' => (float) ($user->farm_size_hectares ?? 0),
            ],
        ]);
    }

    public function mapFields(Request $request): JsonResponse
    {
        $overview = $this->overview($request);
        $data = json_decode($overview->getContent(), true);

        return response()->json(['map' => $data['map'] ?? []]);
    }

    public function addField(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'crop' => ['required', 'string', 'max:255'],
            'areaHectares' => ['nullable', 'numeric', 'min:0'],
            'plantedAt' => ['nullable', 'date'],
        ]);

        $field = FarmField::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'crop' => $validated['crop'],
            'area_hectares' => $validated['areaHectares'] ?? 0,
            'planted_at' => $validated['plantedAt'] ?? null,
        ]);

        return response()->json([
            'field' => [
                'id' => (string) $field->id,
                'name' => $field->name,
                'crop' => $field->crop,
                'area' => $field->area_hectares,
                'health' => $field->health_percentage,
                'moisture' => $field->moisture_percentage,
                'daysSincePlanting' => $field->days_since_planting,
                'status' => $field->status,
                'plantedAt' => $field->planted_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function updateField(Request $request, int $fieldId): JsonResponse
    {
        $field = FarmField::where('user_id', $request->user()->id)
            ->where('id', $fieldId)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'crop' => ['nullable', 'string', 'max:255'],
            'areaHectares' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:100'],
            'healthPercentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'moisturePercentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'plantedAt' => ['nullable', 'date'],
        ]);

        $updateData = [];
        if (isset($validated['name'])) $updateData['name'] = $validated['name'];
        if (isset($validated['crop'])) $updateData['crop'] = $validated['crop'];
        if (isset($validated['areaHectares'])) $updateData['area_hectares'] = $validated['areaHectares'];
        if (isset($validated['status'])) $updateData['status'] = $validated['status'];
        if (isset($validated['healthPercentage'])) $updateData['health_percentage'] = $validated['healthPercentage'];
        if (isset($validated['moisturePercentage'])) $updateData['moisture_percentage'] = $validated['moisturePercentage'];
        if (isset($validated['plantedAt'])) $updateData['planted_at'] = $validated['plantedAt'];

        $field->update($updateData);

        return response()->json([
            'message' => 'Field updated successfully.',
            'field' => [
                'id' => (string) $field->id,
                'name' => $field->name,
                'crop' => $field->crop,
                'area' => $field->area_hectares,
                'health' => $field->health_percentage,
                'moisture' => $field->moisture_percentage,
                'daysSincePlanting' => $field->days_since_planting,
                'status' => $field->status,
            ],
        ]);
    }

    public function deleteField(Request $request, int $fieldId): JsonResponse
    {
        FarmField::where('user_id', $request->user()->id)
            ->where('id', $fieldId)
            ->firstOrFail()
            ->delete();

        return response()->json(['message' => 'Field deleted successfully.']);
    }

    public function addJournalEntry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'max:1000'],
            'type' => ['nullable', 'string', 'max:50'],
            'farmFieldId' => ['nullable', 'integer', 'exists:farm_fields,id'],
        ]);

        $entry = JournalEntry::create([
            'user_id' => $request->user()->id,
            'farm_field_id' => $validated['farmFieldId'] ?? null,
            'type' => $validated['type'] ?? 'observation',
            'note' => $validated['note'],
        ]);

        return response()->json([
            'entry' => [
                'id' => (string) $entry->id,
                'date' => $entry->created_at->toIso8601String(),
                'note' => $entry->note,
                'type' => $entry->type,
                'fieldName' => $entry->farmField?->name,
            ],
        ], 201);
    }

    public function updateJournalEntry(Request $request, int $entryId): JsonResponse
    {
        $entry = JournalEntry::where('user_id', $request->user()->id)
            ->where('id', $entryId)
            ->firstOrFail();

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
            'type' => ['nullable', 'string', 'max:50'],
        ]);

        if (isset($validated['note'])) $entry->note = $validated['note'];
        if (isset($validated['type'])) $entry->type = $validated['type'];
        $entry->save();

        return response()->json(['message' => 'Journal entry updated.']);
    }

    public function deleteJournalEntry(Request $request, int $entryId): JsonResponse
    {
        JournalEntry::where('user_id', $request->user()->id)
            ->where('id', $entryId)
            ->firstOrFail()
            ->delete();

        return response()->json(['message' => 'Journal entry deleted.']);
    }
}
