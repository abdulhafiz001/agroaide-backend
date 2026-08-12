<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FarmImageAnalysis;
use App\Services\CloudinaryStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PrivacyController extends Controller
{
    public function __construct(private CloudinaryStorageService $cloudinary) {}

    public function export(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load([
            'farmFields',
            'journalEntries',
            'calendarTasks',
            'fieldTransactions',
            'advisorConversations',
            'appNotifications',
            'farmImageAnalyses',
            'consents',
        ]);
        $payload = [
            'generatedAt' => now()->toIso8601String(),
            'profile' => $user->makeHidden(['password', 'remember_token', 'push_token', 'phone_normalized']),
            'fields' => $user->farmFields,
            'journal' => $user->journalEntries,
            'tasks' => $user->calendarTasks,
            'transactions' => $user->fieldTransactions,
            'advisorHistory' => $user->advisorConversations,
            'notifications' => $user->appNotifications,
            'scans' => $user->farmImageAnalyses->map->makeHidden(['latitude', 'longitude']),
            'consents' => $user->consents,
        ];

        return response()->json([
            'filename' => 'agroaide-data-export-'.now()->format('Ymd-His').'.json',
            'content' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ])->header('Cache-Control', 'no-store');
    }

    public function deleteScan(Request $request, int $scanId): JsonResponse
    {
        $scan = FarmImageAnalysis::where('user_id', $request->user()->id)->findOrFail($scanId);
        $imagePath = $scan->image_path;
        $publicId = $scan->image_public_id;

        DB::transaction(function () use ($scan): void {
            // Related feedback / reviews cascade, but delete explicitly for clarity.
            DB::table('scan_feedback')->where('farm_image_analysis_id', $scan->id)->delete();
            DB::table('scan_review_history')->where('farm_image_analysis_id', $scan->id)->delete();
            DB::table('system_job_runs')
                ->where('reference_type', FarmImageAnalysis::class)
                ->where('reference_id', $scan->id)
                ->delete();
            $scan->delete();
        });

        $this->deleteScanAsset($publicId, $imagePath);

        return response()->json(['message' => 'Scan and image deleted.']);
    }

    public function clearAdvisorHistory(Request $request): JsonResponse
    {
        $request->user()->advisorConversations()->delete();

        return response()->json(['message' => 'Advisor history cleared.']);
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $validated = $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();
        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => ['The password is incorrect.']]);
        }

        $scans = $user->farmImageAnalyses()->get(['image_path', 'image_public_id']);
        foreach ($scans as $scan) {
            $this->deleteScanAsset($scan->image_public_id, $scan->image_path);
        }
        Storage::disk('local')->deleteDirectory("farm-scans/{$user->id}");
        Storage::disk('local')->deleteDirectory("exports/{$user->id}");
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Account and personal data deleted.']);
    }

    private function deleteScanAsset(?string $publicId, ?string $path): void
    {
        if ($publicId) {
            $this->cloudinary->delete($publicId);
        }

        if (! $path || str_starts_with($path, 'cloudinary:')) {
            return;
        }

        foreach (['local', 'public'] as $disk) {
            Storage::disk($disk)->delete($path);
        }
    }
}
