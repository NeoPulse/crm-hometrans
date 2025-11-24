<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\CaseChatController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\LegalController;
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

    Route::get('/legals', [LegalController::class, 'index'])->name('legals.index');
    Route::post('/legals', [LegalController::class, 'store'])->name('legals.store');
    Route::get('/legals/{legal}', [LegalController::class, 'show'])->name('legals.show');
    Route::put('/legals/{legal}', [LegalController::class, 'update'])->name('legals.update');
    Route::post('/legals/{legal}/password', [LegalController::class, 'generatePassword'])->name('legals.password');

    Route::patch('/cases/{case}/stages/{stage}', [CaseController::class, 'updateStage'])->name('cases.stages.update');
    Route::delete('/cases/{case}/stages/{stage}', [CaseController::class, 'deleteStage'])->name('cases.stages.delete');
    Route::patch('/cases/{case}/tasks/{task}', [CaseController::class, 'updateTask'])->name('cases.tasks.update');
    Route::delete('/cases/{case}/tasks/{task}', [CaseController::class, 'deleteTask'])->name('cases.tasks.delete');
    Route::post('/cases/{case}/tasks/quick', [CaseController::class, 'quickAddTask'])->name('cases.tasks.quick');
});

Route::match(['get', 'post'], '/case/{case}', [CaseController::class, 'publicShow'])->name('cases.public');
Route::get('/case/{case}/chat', [CaseChatController::class, 'index'])->name('cases.chat.index');
Route::post('/case/{case}/chat', [CaseChatController::class, 'store'])->middleware('auth')->name('cases.chat.store');
Route::delete('/case/{case}/chat/{message}', [CaseChatController::class, 'destroy'])->middleware('auth')->name('cases.chat.delete');
