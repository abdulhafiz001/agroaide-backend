<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FarmField;
use App\Models\JournalEntry;
use App\Services\FarmImageAnalysisService;
use App\Services\GeoAreaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FarmController extends Controller
{
    public function __construct(
        private FarmImageAnalysisService $imageAnalysisService,
        private GeoAreaService $geoAreaService,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $fields = $user->farmFields()->orderBy('created_at', 'desc')->get()->map(fn (FarmField $f) => [
            'id' => (string) $f->id,
            'name' => $f->name,
            'crop' => $f->crop,
            'area' => (float) $f->area_m2,
            'health' => $f->health_percentage,
            'moisture' => $f->moisture_percentage,
            'daysSincePlanting' => $f->days_since_planting,
            'status' => $f->status,
            'plantedAt' => $f->planted_at?->toIso8601String(),
            'boundaryGeojson' => $f->boundary_geojson,
            'hasMeasuredBoundary' => ! empty($f->boundary_geojson),
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

        $hasLocation = $user->farm_latitude !== null && $user->farm_longitude !== null;
        $map = null;

        if ($hasLocation) {
            $lat = (float) $user->farm_latitude;
            $lng = (float) $user->farm_longitude;
            $fieldsWithBoundaries = $user->farmFields()
                ->whereNotNull('boundary_geojson')
                ->get();

            $polygons = $fieldsWithBoundaries->map(function (FarmField $field) {
                $coords = $field->boundary_geojson['coordinates'][0] ?? [];
                $ring = collect($coords)->map(fn ($pair) => [
                    'latitude' => (float) ($pair[1] ?? 0),
                    'longitude' => (float) ($pair[0] ?? 0),
                ])->all();

                return [
                    'fieldId' => (string) $field->id,
                    'name' => $field->name,
                    'polygon' => $ring,
                    'geojson' => $field->boundary_geojson,
                ];
            })->values()->all();

            $primaryPolygon = $polygons[0]['polygon'] ?? [
                ['latitude' => $lat + 0.0003, 'longitude' => $lng - 0.0015],
                ['latitude' => $lat + 0.0006, 'longitude' => $lng + 0.0015],
                ['latitude' => $lat - 0.0007, 'longitude' => $lng + 0.002],
                ['latitude' => $lat - 0.001, 'longitude' => $lng - 0.001],
            ];

            $map = [
                'center' => ['latitude' => $lat, 'longitude' => $lng],
                'polygon' => $primaryPolygon,
                'fields' => $polygons,
            ];
        }

        return response()->json([
            'fields' => $fields,
            'journal' => $journal,
            'map' => $map,
            'hasFarmLocation' => $hasLocation,
            'farmSummary' => [
                'farmName' => $user->farm_name ?? 'My Farm',
                'farmLocation' => $user->farm_location ?? 'Unknown location',
                'farmSizeM2' => (float) ($user->farm_size_m2 ?? 0),
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
            'areaM2' => ['nullable', 'numeric', 'min:0'],
            'plantedAt' => ['nullable', 'date'],
            'clientUuid' => ['nullable', 'uuid'],
        ]);

        $field = FarmField::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'crop' => $validated['crop'],
            'area_m2' => $validated['areaM2'] ?? 0,
            'planted_at' => $validated['plantedAt'] ?? null,
            'client_uuid' => $validated['clientUuid'] ?? null,
        ]);

        return response()->json([
            'field' => [
                'id' => (string) $field->id,
                'name' => $field->name,
                'crop' => $field->crop,
                'area' => (float) $field->area_m2,
                'health' => $field->health_percentage,
                'moisture' => $field->moisture_percentage,
                'daysSincePlanting' => $field->days_since_planting,
                'status' => $field->status,
                'plantedAt' => $field->planted_at?->toIso8601String(),
                'boundaryGeojson' => $field->boundary_geojson,
                'hasMeasuredBoundary' => ! empty($field->boundary_geojson),
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
            'areaM2' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:100'],
            'healthPercentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'moisturePercentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'plantedAt' => ['nullable', 'date'],
        ]);

        $updateData = [];
        if (isset($validated['name'])) $updateData['name'] = $validated['name'];
        if (isset($validated['crop'])) $updateData['crop'] = $validated['crop'];
        if (isset($validated['areaM2'])) $updateData['area_m2'] = $validated['areaM2'];
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
                'area' => (float) $field->area_m2,
                'health' => $field->health_percentage,
                'moisture' => $field->moisture_percentage,
                'daysSincePlanting' => $field->days_since_planting,
                'status' => $field->status,
                'boundaryGeojson' => $field->boundary_geojson,
                'hasMeasuredBoundary' => ! empty($field->boundary_geojson),
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

    public function updateBoundary(Request $request, int $fieldId): JsonResponse
    {
        $field = FarmField::where('user_id', $request->user()->id)
            ->where('id', $fieldId)
            ->firstOrFail();

        $validated = $request->validate([
            'geojson' => ['required', 'array'],
            'geojson.type' => ['required', 'in:Polygon'],
            'geojson.coordinates' => ['required', 'array', 'min:1'],
            'areaM2' => ['required', 'numeric', 'min:0'],
            'clientUuid' => ['nullable', 'uuid'],
            'clientTimestamp' => ['nullable', 'date'],
        ]);

        try {
            $serverArea = $this->geoAreaService->areaFromGeoJsonPolygon($validated['geojson']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (! $this->geoAreaService->validateClientArea((float) $validated['areaM2'], $serverArea, 0.1)) {
            return response()->json([
                'message' => 'Client area differs from server-computed area by more than 10%.',
                'clientAreaM2' => (float) $validated['areaM2'],
                'serverAreaM2' => round($serverArea, 2),
            ], 422);
        }

        $field->update([
            'boundary_geojson' => $validated['geojson'],
            'area_m2' => round($serverArea, 2),
            'boundary_updated_at' => isset($validated['clientTimestamp'])
                ? $validated['clientTimestamp']
                : now(),
            'client_uuid' => $validated['clientUuid'] ?? $field->client_uuid,
        ]);

        return response()->json([
            'message' => 'Boundary updated.',
            'field' => [
                'id' => (string) $field->id,
                'name' => $field->name,
                'crop' => $field->crop,
                'area' => (float) $field->area_m2,
                'boundaryGeojson' => $field->boundary_geojson,
                'boundaryUpdatedAt' => $field->boundary_updated_at?->toIso8601String(),
                'hasMeasuredBoundary' => ! empty($field->boundary_geojson),
            ],
        ]);
    }

    public function addJournalEntry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'max:1000'],
            'type' => ['nullable', 'string', 'max:50'],
            'farmFieldId' => ['nullable', 'integer', 'exists:farm_fields,id'],
            'clientUuid' => ['nullable', 'uuid'],
        ]);

        if (! empty($validated['clientUuid'])) {
            $existing = JournalEntry::where('user_id', $request->user()->id)
                ->where('client_uuid', $validated['clientUuid'])
                ->first();
            if ($existing) {
                return response()->json([
                    'entry' => [
                        'id' => (string) $existing->id,
                        'date' => $existing->created_at->toIso8601String(),
                        'note' => $existing->note,
                        'type' => $existing->type,
                        'fieldName' => $existing->farmField?->name,
                        'clientUuid' => $existing->client_uuid,
                    ],
                    'idempotent' => true,
                ]);
            }
        }

        $entry = JournalEntry::create([
            'user_id' => $request->user()->id,
            'client_uuid' => $validated['clientUuid'] ?? null,
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
                'clientUuid' => $entry->client_uuid,
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

    public function analyzeImage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'imageBase64' => ['required', 'string'],
            'farmFieldId' => ['nullable', 'integer', 'exists:farm_fields,id'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        if (isset($validated['farmFieldId'])) {
            $ownsField = FarmField::where('id', $validated['farmFieldId'])
                ->where('user_id', $user->id)
                ->exists();
            if (! $ownsField) {
                return response()->json(['message' => 'Field not found.'], 404);
            }
        }

        $result = $this->imageAnalysisService->analyze(
            $user,
            $validated['imageBase64'],
            $validated['farmFieldId'] ?? null,
        );

        return response()->json([
            'scanId' => $result['scanId'] ?? null,
            'analysis' => $result['analysis'] ?? $result,
        ]);
    }

    public function scanHistory(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json([
            'history' => $this->imageAnalysisService->getHistory($user),
        ]);
    }

    public function scanDetail(Request $request, string $scanId): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $scan = $this->imageAnalysisService->getScanForUser($user, $scanId);

        if (! $scan) {
            return response()->json(['message' => 'Scan not found.'], 404);
        }

        return response()->json(['scan' => $scan]);
    }

    public function scanImage(Request $request, string $scanId)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $response = $this->imageAnalysisService->getImageResponseForUser($user, $scanId);

        if (! $response) {
            return response()->json(['message' => 'Scan image not found.'], 404);
        }

        return $response;
    }
}
