<?php

use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/legal/terms', 'legal.terms')->name('legal.terms');
Route::view('/legal/privacy', 'legal.privacy')->name('legal.privacy');

Route::middleware('guest')->group(function (): void {
    Route::get('/staff/login', [StaffController::class, 'login'])->name('staff.login');
    Route::post('/staff/login', [StaffController::class, 'authenticate'])
        ->middleware('throttle:staff-login')
        ->name('staff.authenticate');
    Route::get('/staff/setup', [StaffController::class, 'setup'])->name('staff.setup');
    Route::post('/staff/setup', [StaffController::class, 'storeSetup'])
        ->middleware('throttle:staff-login')
        ->name('staff.setup.store');
});

Route::middleware(['auth', 'staff'])->prefix('staff')->group(function (): void {
    // Core pages
    Route::get('/', [StaffController::class, 'dashboard'])->name('staff.dashboard');
    Route::post('/logout', [StaffController::class, 'logout'])->name('staff.logout');

    // Scan review
    Route::get('/scans', [StaffController::class, 'scans'])->name('staff.scans.index');
    Route::get('/scans/{scan}', [StaffController::class, 'scanShow'])->name('staff.scans.show');
    Route::get('/scans/{scan}/image', [StaffController::class, 'scanImage'])->name('staff.scans.image');
    Route::post('/scans/{scan}/review', [StaffController::class, 'review'])->name('staff.scans.review');

    // Farmer feedback
    Route::get('/feedback', [StaffController::class, 'feedback'])->name('staff.feedback');

    // Outbreaks
    Route::get('/outbreaks', [StaffController::class, 'outbreaks'])->name('staff.outbreaks');

    // System health
    Route::get('/health', [StaffController::class, 'health'])->name('staff.health');

    // Advanced: evaluations
    Route::get('/evaluations', [StaffController::class, 'evaluations'])->name('staff.evaluations.index');
    Route::get('/evaluations/compare', [StaffController::class, 'compare'])->name('staff.evaluations.compare');
    Route::get('/evaluations/datasets/{dataset}', [StaffController::class, 'dataset'])->name('staff.evaluations.datasets.show');
    Route::get('/evaluations/runs/{run}', [StaffController::class, 'run'])->name('staff.evaluations.runs.show');

    // Admin-only routes
    Route::middleware('staff:admin')->group(function (): void {
        Route::get('/admin', [StaffController::class, 'admin'])->name('staff.admin');
        Route::get('/audit', [StaffController::class, 'audit'])->name('staff.audit');
        Route::post('/evaluations/datasets/{dataset}/runs', [StaffController::class, 'queueRun'])->name('staff.evaluations.runs.store');
        Route::post('/confidence-policies', [StaffController::class, 'createPolicy'])->name('staff.policies.store');
        Route::post('/confidence-policies/{policy}/activate', [StaffController::class, 'activatePolicy'])->name('staff.policies.activate');
        Route::patch('/users/{user}/role', [StaffController::class, 'assignRole'])->name('staff.users.role');
    });
});
