<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdvisorController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EconomicsController;
use App\Http\Controllers\Api\FarmController;
use App\Http\Controllers\Api\MarketController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OutbreakController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\WeatherController;
use Illuminate\Support\Facades\Route;

// Quick connectivity check - no auth, no DB, returns immediately
Route::get('/health', fn () => response()->json(['ok' => true, 'message' => 'AgroAide API is reachable']));

// Debug: test GitHub Models (dev only, no auth)
Route::get('/debug/github-models-test', function () {
    if (! app()->environment('local')) {
        return response()->json(['error' => 'Not available'], 404);
    }
    $key = config('services.github_models.api_key', '');
    $model = config('services.github_models.model', 'openai/gpt-4o-mini');
    $endpoint = config('services.github_models.endpoint', 'https://models.github.ai/inference/chat/completions');
    $apiVersion = config('services.github_models.api_version', '2022-11-28');
    if (empty($key)) {
        return response()->json(['ok' => false, 'error' => 'GITHUB_MODELS_API_KEY not set in .env']);
    }
    try {
        $r = \Illuminate\Support\Facades\Http::timeout(30)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $key,
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => $apiVersion,
                'Content-Type' => 'application/json',
            ])
            ->post($endpoint, [
                'model' => $model,
                'messages' => [['role' => 'user', 'content' => 'Say "OK" if you can read this.']],
                'max_tokens' => 20,
            ]);
        $status = $r->status();
        $body = $r->json();
        if ($r->successful()) {
            return response()->json(['ok' => true, 'reply' => $body['choices'][0]['message']['content'] ?? '?']);
        }
        return response()->json(['ok' => false, 'status' => $status, 'error' => $body['error'] ?? $r->body()]);
    } catch (\Exception $e) {
        return response()->json(['ok' => false, 'error' => $e->getMessage()]);
    }
});

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/recovery', [AuthController::class, 'requestPasswordReset']);
    Route::post('/recovery/reset', [AuthController::class, 'resetPasswordWithCode']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/farm/overview', [FarmController::class, 'overview']);
    Route::post('/farm/fields', [FarmController::class, 'addField']);
    Route::put('/farm/fields/{fieldId}', [FarmController::class, 'updateField']);
    Route::delete('/farm/fields/{fieldId}', [FarmController::class, 'deleteField']);
    Route::put('/farm/fields/{fieldId}/boundary', [FarmController::class, 'updateBoundary']);
    Route::post('/farm/journal', [FarmController::class, 'addJournalEntry']);
    Route::put('/farm/journal/{entryId}', [FarmController::class, 'updateJournalEntry']);
    Route::delete('/farm/journal/{entryId}', [FarmController::class, 'deleteJournalEntry']);
    Route::get('/map/fields', [FarmController::class, 'mapFields']);
    Route::post('/farm/analyze-image', [FarmController::class, 'analyzeImage']);
    Route::get('/farm/scan-history', [FarmController::class, 'scanHistory']);
    Route::get('/farm/scan-history/{scanId}/image', [FarmController::class, 'scanImage']);
    Route::get('/farm/scan-history/{scanId}', [FarmController::class, 'scanDetail']);

    Route::get('/farm/fields/{fieldId}/transactions', [EconomicsController::class, 'listTransactions']);
    Route::post('/farm/fields/{fieldId}/transactions', [EconomicsController::class, 'createTransaction']);
    Route::put('/transactions/{id}', [EconomicsController::class, 'updateTransaction']);
    Route::delete('/transactions/{id}', [EconomicsController::class, 'deleteTransaction']);
    Route::get('/farm/fields/{fieldId}/economics', [EconomicsController::class, 'fieldEconomics']);
    Route::get('/farm/economics/summary', [EconomicsController::class, 'summary']);
    Route::get('/farm/fields/{fieldId}/economics/export', [EconomicsController::class, 'export']);

    Route::get('/calendar', [CalendarController::class, 'index']);
    Route::post('/calendar/tasks', [CalendarController::class, 'store']);
    Route::put('/calendar/tasks/{taskId}', [CalendarController::class, 'update']);
    Route::delete('/calendar/tasks/{taskId}', [CalendarController::class, 'destroy']);
    Route::post('/calendar/tasks/{taskId}/complete', [CalendarController::class, 'completeTask']);
    Route::get('/calendar/seasonal-suggestions', [CalendarController::class, 'seasonalSuggestions']);
    Route::get('/calendar/crop-watches', [CalendarController::class, 'listCropWatches']);
    Route::post('/calendar/crop-watches', [CalendarController::class, 'storeCropWatch']);
    Route::delete('/calendar/crop-watches/{id}', [CalendarController::class, 'destroyCropWatch']);

    Route::post('/sync/delta', [SyncController::class, 'delta']);
    Route::get('/sync/pull', [SyncController::class, 'pull']);

    Route::get('/weather/forecast', [WeatherController::class, 'forecast']);
    Route::post('/advisor/chat', [AdvisorController::class, 'chat']);
    Route::get('/advisor/history', [AdvisorController::class, 'history']);
    Route::get('/advisor/suggestions', [AdvisorController::class, 'suggestions']);
    Route::get('/advisor/daily-insight', [AdvisorController::class, 'dailyInsight']);
    Route::post('/advisor/transcribe', [AdvisorController::class, 'transcribeVoice']);
    Route::get('/dashboard/snapshot', [DashboardController::class, 'snapshot']);
    Route::get('/dashboard/ai-insights', [DashboardController::class, 'aiInsights']);
    Route::get('/market/intel', [MarketController::class, 'intel']);
    Route::get('/market/resources', [MarketController::class, 'resources']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    Route::get('/outbreak/heatmap', [OutbreakController::class, 'heatmap']);
    Route::get('/outbreak/alerts', [OutbreakController::class, 'alerts']);
});
