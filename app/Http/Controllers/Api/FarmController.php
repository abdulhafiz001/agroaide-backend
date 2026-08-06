<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FarmField;
use App\Models\FarmImageAnalysis;
use App\Models\FieldTransaction;
use App\Models\JournalEntry;
use App\Models\ScanFeedback;
use App\Models\User;
use App\Services\FarmImageAnalysisService;
use App\Services\GeoAreaService;
use App\Services\InputEstimateService;
use App\Services\ScanVerificationService;
use App\Support\MediaPayloadValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FarmController extends Controller
{
    public function __construct(
        private FarmImageAnalysisService $imageAnalysisService,
        private GeoAreaService $geoAreaService,
        private InputEstimateService $inputEstimateService,
        private MediaPayloadValidator $mediaValidator,
        private ScanVerificationService $scanVerification,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $economicsByField = FieldTransaction::where('user_id', $user->id)
            ->select(
                'farm_field_id',
                DB::raw("COALESCE(SUM(CASE WHEN type = 'EXPENSE' THEN amount ELSE 0 END), 0) as total_expense"),
                DB::raw("COALESCE(SUM(CASE WHEN type = 'INCOME' THEN amount ELSE 0 END), 0) as total_income"),
            )
            ->groupBy('farm_field_id')
            ->get()
            ->keyBy('farm_field_id');

        $fields = $user->farmFields()->orderBy('created_at', 'desc')->get()->map(function (FarmField $f) use ($economicsByField) {
            $eco = $economicsByField->get($f->id);
            $totalExpense = round((float) ($eco->total_expense ?? 0), 2);
            $totalIncome = round((float) ($eco->total_income ?? 0), 2);

            return [
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
                'totalExpense' => $totalExpense,
                'totalIncome' => $totalIncome,
                'netProfit' => round($totalIncome - $totalExpense, 2),
            ];
        });

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
                    'crop' => $field->crop,
                    'polygon' => $ring,
                    'geojson' => $field->boundary_geojson,
                ];
            })->values()->all();

            // Farm outline from registered size (square plot centered on farm GPS).
            $farmPolygon = $this->geoAreaService->squarePolygonAround(
                $lat,
                $lng,
                (float) ($user->farm_size_m2 ?? 0),
            );

            $map = [
                'center' => ['latitude' => $lat, 'longitude' => $lng],
                'polygon' => $farmPolygon,
                'farmName' => $user->farm_name ?? 'My Farm',
                'farmSizeM2' => (float) ($user->farm_size_m2 ?? 0),
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
        if (isset($validated['name'])) {
            $updateData['name'] = $validated['name'];
        }
        if (isset($validated['crop'])) {
            $updateData['crop'] = $validated['crop'];
        }
        if (isset($validated['areaM2'])) {
            $updateData['area_m2'] = $validated['areaM2'];
        }
        if (isset($validated['status'])) {
            $updateData['status'] = $validated['status'];
        }
        if (isset($validated['healthPercentage'])) {
            $updateData['health_percentage'] = $validated['healthPercentage'];
        }
        if (isset($validated['moisturePercentage'])) {
            $updateData['moisture_percentage'] = $validated['moisturePercentage'];
        }
        if (isset($validated['plantedAt'])) {
            $updateData['planted_at'] = $validated['plantedAt'];
        }

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
                'plantedAt' => $field->planted_at?->toIso8601String(),
                'boundaryGeojson' => $field->boundary_geojson,
                'hasMeasuredBoundary' => ! empty($field->boundary_geojson),
            ],
        ]);
    }

    public function showField(Request $request, int $fieldId): JsonResponse
    {
        $field = FarmField::where('user_id', $request->user()->id)
            ->where('id', $fieldId)
            ->firstOrFail();

        $eco = FieldTransaction::where('user_id', $request->user()->id)
            ->where('farm_field_id', $field->id)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'EXPENSE' THEN amount ELSE 0 END), 0) as total_expense,
                COALESCE(SUM(CASE WHEN type = 'INCOME' THEN amount ELSE 0 END), 0) as total_income
            ")
            ->first();

        $totalExpense = round((float) ($eco->total_expense ?? 0), 2);
        $totalIncome = round((float) ($eco->total_income ?? 0), 2);

        $user = $request->user();
        $boundaryRing = [];
        if (! empty($field->boundary_geojson['coordinates'][0])) {
            $boundaryRing = collect($field->boundary_geojson['coordinates'][0])->map(fn ($pair) => [
                'latitude' => (float) ($pair[1] ?? 0),
                'longitude' => (float) ($pair[0] ?? 0),
            ])->all();
        }

        $farmPolygon = null;
        $center = null;
        if ($user->farm_latitude !== null && $user->farm_longitude !== null) {
            $lat = (float) $user->farm_latitude;
            $lng = (float) $user->farm_longitude;
            $center = ['latitude' => $lat, 'longitude' => $lng];
            $farmPolygon = $this->geoAreaService->squarePolygonAround(
                $lat,
                $lng,
                (float) ($user->farm_size_m2 ?? 0),
            );
        } elseif (count($boundaryRing) >= 3) {
            $center = $boundaryRing[0];
        }

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
                'totalExpense' => $totalExpense,
                'totalIncome' => $totalIncome,
                'netProfit' => round($totalIncome - $totalExpense, 2),
            ],
            'farmSummary' => [
                'farmName' => $user->farm_name ?? 'My Farm',
                'farmLocation' => $user->farm_location ?? 'Unknown location',
                'farmSizeM2' => (float) ($user->farm_size_m2 ?? 0),
            ],
            'map' => $center ? [
                'center' => $center,
                'polygon' => $farmPolygon ?? [],
                'farmName' => $user->farm_name ?? 'My Farm',
                'fields' => count($boundaryRing) >= 3 ? [[
                    'fieldId' => (string) $field->id,
                    'name' => $field->name,
                    'crop' => $field->crop,
                    'polygon' => $boundaryRing,
                ]] : [],
            ] : null,
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

    public function clearBoundary(Request $request, int $fieldId): JsonResponse
    {
        $field = FarmField::where('user_id', $request->user()->id)
            ->where('id', $fieldId)
            ->firstOrFail();

        $field->update([
            'boundary_geojson' => null,
            'boundary_updated_at' => null,
            'boundary_reminder_sent_at' => null,
        ]);

        return response()->json([
            'message' => 'Field boundary removed. You can walk a new boundary anytime.',
            'field' => [
                'id' => (string) $field->id,
                'name' => $field->name,
                'hasMeasuredBoundary' => false,
                'area' => (float) $field->area_m2,
            ],
        ]);
    }

    public function inputEstimate(Request $request, int $fieldId): JsonResponse
    {
        $field = FarmField::where('user_id', $request->user()->id)
            ->where('id', $fieldId)
            ->firstOrFail();

        $validated = $request->validate([
            'rowCm' => ['nullable', 'numeric', 'min:1', 'max:1000'],
            'intraCm' => ['nullable', 'numeric', 'min:1', 'max:1000'],
            'spacingMode' => ['nullable', 'in:cm,steps'],
        ]);

        try {
            $estimate = $this->inputEstimateService->estimate(
                $field,
                $request->user(),
                isset($validated['rowCm']) ? (float) $validated['rowCm'] : null,
                isset($validated['intraCm']) ? (float) $validated['intraCm'] : null,
                $validated['spacingMode'] ?? 'cm',
            );

            return response()->json(['estimate' => $estimate]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Could not calculate seed and fertilizer estimate.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
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
            'farmFieldId' => ['nullable', 'integer', Rule::exists('farm_fields', 'id')->where('user_id', $request->user()->id)],
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
            'farmFieldId' => ['nullable', 'integer', Rule::exists('farm_fields', 'id')->where('user_id', $request->user()->id)],
        ]);

        if (isset($validated['note'])) {
            $entry->note = $validated['note'];
        }
        if (isset($validated['type'])) {
            $entry->type = $validated['type'];
        }
        if (array_key_exists('farmFieldId', $validated)) {
            $entry->farm_field_id = $validated['farmFieldId'];
        }
        $entry->save();
        $entry->load('farmField:id,name');

        return response()->json([
            'message' => 'Journal entry updated.',
            'entry' => [
                'id' => (string) $entry->id,
                'date' => $entry->created_at->toIso8601String(),
                'note' => $entry->note,
                'type' => $entry->type,
                'farmFieldId' => $entry->farm_field_id ? (string) $entry->farm_field_id : null,
                'fieldName' => $entry->farmField?->name,
                'clientUuid' => $entry->client_uuid,
            ],
        ]);
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
            'farmFieldId' => ['nullable', 'integer', Rule::exists('farm_fields', 'id')->where('user_id', $request->user()->id)],
        ]);

        /** @var User $user */
        $user = $request->user();

        $media = $this->mediaValidator->image($validated['imageBase64']);

        $result = $this->imageAnalysisService->analyze(
            $user,
            $media['dataUrl'],
            $validated['farmFieldId'] ?? null,
        );

        return response()->json([
            'scanId' => $result['scanId'],
            'scan' => $result['scan'],
        ], 202);
    }

    public function scanHistory(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'history' => $this->imageAnalysisService->getHistory($user),
        ]);
    }

    public function scanDetail(Request $request, string $scanId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $scan = $this->imageAnalysisService->getScanForUser($user, $scanId);

        if (! $scan) {
            return response()->json(['message' => 'Scan not found.'], 404);
        }

        return response()->json(['scan' => $scan]);
    }

    public function scanImage(Request $request, string $scanId)
    {
        /** @var User $user */
        $user = $request->user();
        $response = $this->imageAnalysisService->getImageResponseForUser($user, $scanId);

        if (! $response) {
            return response()->json(['message' => 'Scan image not found.'], 404);
        }

        return $response;
    }

    public function scanFeedback(Request $request, string $scanId): JsonResponse
    {
        $validated = $request->validate([
            'verdict' => ['required', Rule::in(['correct', 'incorrect', 'unsure'])],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);
        $scan = FarmImageAnalysis::where('user_id', $request->user()->id)->findOrFail($scanId);
        $feedback = ScanFeedback::updateOrCreate(
            [
                'farm_image_analysis_id' => $scan->id,
                'user_id' => $request->user()->id,
            ],
            [
                'verdict' => $validated['verdict'],
                'comment' => $validated['comment'] ?? null,
            ],
        );
        if (in_array($validated['verdict'], ['incorrect', 'unsure'], true)
            && $scan->verification_state !== 'disputed'
            && $this->scanVerification->canTransition($scan->verification_state, 'disputed')) {
            $scan = $this->scanVerification->transition(
                $scan,
                'disputed',
                $request->user(),
                reason: 'farmer_'.$validated['verdict'],
            );
        }

        return response()->json([
            'feedbackId' => (string) $feedback->id,
            'scan' => [
                'id' => (string) $scan->id,
                'verificationState' => $scan->verification_state,
                'outbreakEligible' => (bool) $scan->outbreak_eligible,
            ],
        ], $feedback->wasRecentlyCreated ? 201 : 200);
    }
}
