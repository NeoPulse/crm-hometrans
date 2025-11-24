<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('cases', CaseController::class)->only(['index', 'store', 'edit', 'update']);
    Route::post('/cases/{case}/stages', [CaseController::class, 'addStage'])->name('cases.stages.store');
    Route::post('/cases/{case}/tasks', [CaseController::class, 'addTask'])->name('cases.tasks.store');

    Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
    Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
    Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');
    Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
    Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');
    Route::post('/clients/{client}/attention/{type}', [ClientController::class, 'toggleAttention'])->name('clients.attention');
});
