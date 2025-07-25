<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Include temporary setup routes
require __DIR__.'/temp.php';

Route::get('/', function () {
    return view('welcome');
});

// Dashboard routes (protected by auth)
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        return redirect()->route('dashboard.global');
    })->name('dashboard');
    
    Route::get('/dashboard/global', [App\Http\Controllers\DashboardController::class, 'globalDashboard'])
        ->name('dashboard.global');
    
    Route::get('/dashboard/gateway/{gateway}', [App\Http\Controllers\DashboardController::class, 'gatewayDashboard'])
        ->name('dashboard.gateway');
});
