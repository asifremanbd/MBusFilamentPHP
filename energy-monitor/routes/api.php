<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ReadingController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Dashboard API routes (protected by auth)
Route::middleware(['auth:sanctum'])->prefix('dashboard')->group(function () {
    Route::get('/config', [App\Http\Controllers\Api\DashboardConfigController::class, 'getConfig']);
    Route::post('/config/widget/visibility', [App\Http\Controllers\Api\DashboardConfigController::class, 'updateWidgetVisibility']);
    Route::post('/config/widget/layout', [App\Http\Controllers\Api\DashboardConfigController::class, 'updateWidgetLayout']);
    Route::post('/config/widget/config', [App\Http\Controllers\Api\DashboardConfigController::class, 'updateWidgetConfig']);
    Route::post('/config/reset', [App\Http\Controllers\Api\DashboardConfigController::class, 'resetConfig']);
    Route::get('/widgets/available', [App\Http\Controllers\Api\DashboardConfigController::class, 'getAvailableWidgets']);
    Route::get('/widgets/performance', [App\Http\Controllers\Api\DashboardConfigController::class, 'getWidgetPerformance']);
    Route::post('/widgets/cache/clear', [App\Http\Controllers\Api\DashboardConfigController::class, 'clearWidgetCache']);
    Route::get('/gateways', [App\Http\Controllers\Api\DashboardConfigController::class, 'getAuthorizedGateways']);
});

// RTU Dashboard API routes (protected by auth)
Route::middleware(['auth:sanctum'])->prefix('rtu')->group(function () {
    Route::get('/gateway/{gateway}/data', [App\Http\Controllers\RTUDashboardController::class, 'getRTUDashboardData'])
        ->name('api.rtu.dashboard.data');
    Route::get('/gateway/{gateway}/status', [App\Http\Controllers\RTUDashboardController::class, 'getRTUStatus'])
        ->name('api.rtu.status');
    Route::post('/gateways/{gateway}/digital-output/{output}', [App\Http\Controllers\RTUDashboardController::class, 'updateDigitalOutput'])
        ->name('api.rtu.output.update');
    Route::post('/gateway/{gateway}/alerts/filter', [App\Http\Controllers\RTUDashboardController::class, 'filterAlerts'])
        ->name('api.rtu.alerts.filter');
    
    // RTU Dashboard Section Management
    Route::get('/sections', [App\Http\Controllers\RTUDashboardSectionController::class, 'getSections'])
        ->name('api.rtu.sections.get');
    Route::post('/sections/update', [App\Http\Controllers\RTUDashboardSectionController::class, 'updateSectionState'])
        ->name('api.rtu.sections.update');
    Route::post('/sections/reset', [App\Http\Controllers\RTUDashboardSectionController::class, 'resetSections'])
        ->name('api.rtu.sections.reset');
    
    // RTU Preferences Management
    Route::get('/gateway/{gateway}/preferences', [App\Http\Controllers\RTUPreferencesController::class, 'getTrendPreferences'])
        ->name('api.rtu.preferences.get');
    Route::post('/gateway/{gateway}/preferences', [App\Http\Controllers\RTUPreferencesController::class, 'updateTrendPreferences'])
        ->name('api.rtu.preferences.update');
    Route::post('/gateway/{gateway}/preferences/reset', [App\Http\Controllers\RTUPreferencesController::class, 'resetToDefaults'])
        ->name('api.rtu.preferences.reset');
    Route::delete('/gateway/{gateway}/preferences', [App\Http\Controllers\RTUPreferencesController::class, 'deletePreferences'])
        ->name('api.rtu.preferences.delete');
    Route::get('/preferences/config', [App\Http\Controllers\RTUPreferencesController::class, 'getConfigOptions'])
        ->name('api.rtu.preferences.config');
    Route::post('/preferences/bulk', [App\Http\Controllers\RTUPreferencesController::class, 'getBulkPreferences'])
        ->name('api.rtu.preferences.bulk');
    Route::get('/preferences/export', [App\Http\Controllers\RTUPreferencesController::class, 'exportPreferences'])
        ->name('api.rtu.preferences.export');
    Route::post('/preferences/import', [App\Http\Controllers\RTUPreferencesController::class, 'importPreferences'])
        ->name('api.rtu.preferences.import');
});

// Permission API routes (protected by auth)
Route::middleware(['auth:sanctum'])->prefix('permissions')->group(function () {
    Route::get('/check', [App\Http\Controllers\Api\PermissionController::class, 'checkPermissions']);
    Route::post('/refresh', [App\Http\Controllers\Api\PermissionController::class, 'refreshPermissions']);
});

// Reading endpoint for Python poller (temporarily without auth for testing)
Route::post('/readings', [ReadingController::class, 'store']);

// Health check endpoint (no auth required)
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'service' => 'Energy Monitor API'
    ]);
});
