<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OllamaChatController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard/snapshot', [DashboardController::class, 'snapshot'])->name('dashboard.snapshot');
Route::post('/services', [DashboardController::class, 'storeService'])->name('services.store');
Route::get('/services/{service}/report', [DashboardController::class, 'serviceReport'])->name('services.report');
Route::get('/services/{service}/check', [DashboardController::class, 'checkService'])
    ->middleware('throttle:30,1')
    ->name('services.check');
Route::post('/services/{service}/documentation', [DashboardController::class, 'updateDocumentation'])->name('services.documentation.update');
Route::post('/ollama/chat', OllamaChatController::class)->middleware('throttle:10,1')->name('ollama.chat');
Route::get('/incidents/{incident}', [DashboardController::class, 'incident'])->name('incidents.show');
Route::post('/services/{service}/chaos', [DashboardController::class, 'chaos'])
    ->middleware('throttle:2,1')
    ->name('services.chaos');
