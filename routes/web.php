<?php

use App\Http\Controllers\CaseController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('cases', CaseController::class)->only(['index', 'store', 'edit', 'update']);
    Route::post('/cases/{case}/stages', [CaseController::class, 'addStage'])->name('cases.stages.store');
    Route::post('/cases/{case}/tasks', [CaseController::class, 'addTask'])->name('cases.tasks.store');
});

Route::match(['get', 'post'], '/cases/public/{case}', [CaseController::class, 'publicShow'])
    ->name('cases.public.show');
