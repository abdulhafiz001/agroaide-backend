<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdvisorController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FarmController;
use App\Http\Controllers\Api\MarketController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Controllers\Api\WeatherController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/recovery', [AuthController::class, 'requestPasswordReset']);

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
    Route::post('/farm/journal', [FarmController::class, 'addJournalEntry']);
    Route::put('/farm/journal/{entryId}', [FarmController::class, 'updateJournalEntry']);
    Route::delete('/farm/journal/{entryId}', [FarmController::class, 'deleteJournalEntry']);
    Route::get('/map/fields', [FarmController::class, 'mapFields']);

    Route::get('/calendar', [CalendarController::class, 'index']);
    Route::post('/calendar/tasks', [CalendarController::class, 'store']);
    Route::put('/calendar/tasks/{taskId}', [CalendarController::class, 'update']);
    Route::delete('/calendar/tasks/{taskId}', [CalendarController::class, 'destroy']);
    Route::post('/calendar/tasks/{taskId}/complete', [CalendarController::class, 'completeTask']);
    Route::get('/weather/forecast', [WeatherController::class, 'forecast']);
    Route::post('/advisor/chat', [AdvisorController::class, 'chat']);
    Route::get('/advisor/suggestions', [AdvisorController::class, 'suggestions']);
    Route::get('/advisor/daily-insight', [AdvisorController::class, 'dailyInsight']);
    Route::get('/dashboard/snapshot', [DashboardController::class, 'snapshot']);
    Route::get('/market/intel', [MarketController::class, 'intel']);
    Route::get('/market/nearby-farmers', [MarketController::class, 'nearbyFarmers']);
    Route::get('/market/resources', [MarketController::class, 'resources']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    Route::post('/system/sync-offline', [SystemController::class, 'syncOfflineBrief']);
    Route::post('/system/export-request', [SystemController::class, 'requestExport']);
    Route::get('/system/support-links', [SystemController::class, 'supportLinks']);
});
