<?php

use App\Http\Controllers\Api\AdvisorController;
use App\Http\Controllers\Api\AppRatingController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EconomicsController;
use App\Http\Controllers\Api\FarmController;
use App\Http\Controllers\Api\LegalController;
use App\Http\Controllers\Api\MarketController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OutbreakController;
use App\Http\Controllers\Api\PrivacyController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\WeatherController;
use Illuminate\Support\Facades\Route;

// Quick connectivity check - no auth, no DB, returns immediately
Route::get('/health', fn () => response()->json(['ok' => true, 'message' => 'AgroAide API is reachable']));

Route::get('/farm/exports/{userId}/{file}', [EconomicsController::class, 'downloadExport'])
    ->middleware('signed')
    ->name('economics.export.download')
    ->where(['userId' => '[0-9]+', 'file' => 'field-[A-Za-z0-9._-]+\.pdf']);

Route::get('/legal', [LegalController::class, 'metadata']);

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/recovery', [AuthController::class, 'requestPasswordReset'])->middleware('throttle:recovery');
    Route::post('/recovery/reset', [AuthController::class, 'resetPasswordWithCode'])->middleware('throttle:recovery');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile'])->middleware('consent.current');
        // Push token must work even when legal re-consent is pending (428 on /profile).
        Route::post('/push-token', [AuthController::class, 'registerPushToken']);
        Route::post('/change-password', [AuthController::class, 'changePassword'])->middleware('consent.current');
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/consent', [LegalController::class, 'consent']);
        Route::delete('/account', [PrivacyController::class, 'deleteAccount']);
    });
});

Route::middleware(['auth:sanctum', 'consent.current'])->group(function (): void {
    Route::get('/farm/overview', [FarmController::class, 'overview']);
    Route::get('/farm/planting-prompt', [FarmController::class, 'plantingPrompt']);
    Route::post('/farm/planting-prompt/dismiss', [FarmController::class, 'dismissPlantingPrompt']);
    Route::post('/farm/fields/{fieldId}/planted-at', [FarmController::class, 'recordPlantedAt']);
    Route::post('/farm/fields/{fieldId}/harvest', [FarmController::class, 'markHarvested']);
    Route::post('/farm/fields/{fieldId}/plan-next-crop', [FarmController::class, 'planNextCrop']);
    Route::post('/app/ratings', [AppRatingController::class, 'store']);
    Route::get('/farm/fields/{fieldId}', [FarmController::class, 'showField']);
    Route::post('/farm/fields', [FarmController::class, 'addField']);
    Route::put('/farm/fields/{fieldId}', [FarmController::class, 'updateField']);
    Route::delete('/farm/fields/{fieldId}', [FarmController::class, 'deleteField']);
    Route::delete('/farm/fields/{fieldId}/boundary', [FarmController::class, 'clearBoundary']);
    Route::post('/farm/fields/{fieldId}/input-estimate', [FarmController::class, 'inputEstimate']);
    Route::get('/farm/fields/{fieldId}/input-estimates', [FarmController::class, 'inputEstimateHistory']);
    Route::delete('/farm/input-estimates/{historyId}', [FarmController::class, 'deleteInputEstimate']);
    Route::put('/farm/fields/{fieldId}/boundary', [FarmController::class, 'updateBoundary']);
    Route::post('/farm/journal', [FarmController::class, 'addJournalEntry']);
    Route::put('/farm/journal/{entryId}', [FarmController::class, 'updateJournalEntry']);
    Route::delete('/farm/journal/{entryId}', [FarmController::class, 'deleteJournalEntry']);
    Route::get('/map/fields', [FarmController::class, 'mapFields']);
    Route::post('/farm/analyze-image', [FarmController::class, 'analyzeImage'])->middleware('throttle:scan');
    Route::post('/farm/scans', [FarmController::class, 'analyzeImage'])->middleware('throttle:scan');
    Route::get('/farm/scan-history', [FarmController::class, 'scanHistory']);
    Route::get('/farm/scan-history/{scanId}/image', [FarmController::class, 'scanImage']);
    Route::get('/farm/scan-history/{scanId}', [FarmController::class, 'scanDetail']);
    Route::delete('/farm/scan-history/{scanId}', [PrivacyController::class, 'deleteScan']);
    Route::get('/farm/scans/{scanId}', [FarmController::class, 'scanDetail']);
    Route::post('/farm/scans/{scanId}/feedback', [FarmController::class, 'scanFeedback'])->middleware('throttle:feedback');

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
    Route::post('/calendar/planting-reminders', [CalendarController::class, 'setPlantingReminder']);

    Route::post('/sync/delta', [SyncController::class, 'delta'])->middleware('throttle:sync');
    Route::get('/sync/pull', [SyncController::class, 'pull'])->middleware('throttle:sync');

    Route::get('/weather/forecast', [WeatherController::class, 'forecast']);
    Route::post('/advisor/chat', [AdvisorController::class, 'chat'])->middleware('throttle:chat');
    Route::get('/advisor/history', [AdvisorController::class, 'history']);
    Route::get('/advisor/suggestions', [AdvisorController::class, 'suggestions']);
    Route::get('/advisor/daily-insight', [AdvisorController::class, 'dailyInsight']);
    Route::post('/advisor/transcribe', [AdvisorController::class, 'transcribeVoice'])->middleware('throttle:transcription');
    Route::delete('/advisor/history', [PrivacyController::class, 'clearAdvisorHistory']);
    Route::get('/dashboard/snapshot', [DashboardController::class, 'snapshot']);
    Route::get('/dashboard/ai-insights', [DashboardController::class, 'aiInsights']);
    Route::get('/market/intel', [MarketController::class, 'intel']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    Route::get('/outbreak/heatmap', [OutbreakController::class, 'heatmap']);
    Route::get('/outbreak/alerts', [OutbreakController::class, 'alerts']);
    Route::get('/privacy/export', [PrivacyController::class, 'export'])->middleware('throttle:export');
});
