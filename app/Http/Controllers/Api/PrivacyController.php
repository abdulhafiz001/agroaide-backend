<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FarmImageAnalysis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PrivacyController extends Controller
{
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
        $this->deleteScanFile($scan->image_path);
        $scan->delete();

        return response()->json(['message' => 'Scan deleted.']);
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

        foreach ($user->farmImageAnalyses()->pluck('image_path') as $path) {
            $this->deleteScanFile($path);
        }
        Storage::disk('local')->deleteDirectory("farm-scans/{$user->id}");
        Storage::disk('local')->deleteDirectory("exports/{$user->id}");
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Account and personal data deleted.']);
    }

    private function deleteScanFile(?string $path): void
    {
        if (! $path) {
            return;
        }
        foreach (['local', 'public'] as $disk) {
            Storage::disk($disk)->delete($path);
        }
    }
}
