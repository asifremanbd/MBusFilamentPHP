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
