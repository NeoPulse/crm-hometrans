<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\CaseManagerController;
use App\Http\Controllers\CaseStageController;
use App\Http\Controllers\CaseChatController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Public route serving the login form for guests.
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'authenticate'])->name('login.process');
});

// Protected routes that require an authenticated session.
Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        // Redirect authenticated users to their respective workspace based on role.
        if (auth()->user()->role === 'legal') {
            return redirect()->route('casemanager.legal');
        }

        // Admins default to the dashboard overview.
        return redirect()->route('dashboard');
    })->name('home');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Administrative dashboard entry point.
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile section for administrators and legal users to update their password.
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Case manager endpoints for admin and legal users.
    Route::get('/casemanager', [CaseManagerController::class, 'index'])->name('casemanager.index');
    Route::post('/casemanager', [CaseManagerController::class, 'store'])->name('casemanager.store');
    Route::get('/casemanager/legal', [CaseManagerController::class, 'legalIndex'])->name('casemanager.legal');
    Route::get('/casemanager/{caseFile}/edit', [CaseManagerController::class, 'edit'])->name('casemanager.edit');
    Route::post('/casemanager/{caseFile}/participants', [CaseManagerController::class, 'updateParticipants'])->name('casemanager.participants');
    Route::post('/casemanager/{caseFile}/details', [CaseManagerController::class, 'updateDetails'])->name('casemanager.details');
    Route::post('/casemanager/{caseFile}/attention/{type}', [CaseManagerController::class, 'toggleAttention'])->name('casemanager.attention');
    Route::get('/casemanager/user-search', [CaseManagerController::class, 'searchUsers'])->name('casemanager.usersearch');

    // Case stage workspace with inline stage and task management.
    Route::get('/case/{caseFile}', [CaseStageController::class, 'show'])->name('cases.show');
    Route::post('/case/{caseFile}/stages', [CaseStageController::class, 'storeStage'])->name('cases.stages.store');
    Route::put('/stages/{stage}', [CaseStageController::class, 'updateStage'])->name('cases.stages.update');
    Route::delete('/stages/{stage}', [CaseStageController::class, 'destroyStage'])->name('cases.stages.destroy');
    Route::post('/stages/{stage}/tasks', [CaseStageController::class, 'storeTask'])->name('cases.tasks.store');
    Route::put('/tasks/{task}', [CaseStageController::class, 'updateTask'])->name('cases.tasks.update');
    Route::delete('/tasks/{task}', [CaseStageController::class, 'destroyTask'])->name('cases.tasks.destroy');

    // Case chat routes for viewing, posting, and managing chat messages.
    Route::get('/case/{caseFile}/chat', [CaseChatController::class, 'index'])->name('cases.chat.index');
    Route::get('/case/{caseFile}/chat/unread-count', [CaseChatController::class, 'unreadCount'])->name('cases.chat.unread');
    Route::post('/case/{caseFile}/chat', [CaseChatController::class, 'store'])->name('cases.chat.store');
    Route::delete('/case/{caseFile}/chat/{chatMessage}', [CaseChatController::class, 'destroy'])->name('cases.chat.destroy');
    Route::get('/case/{caseFile}/chat/{chatMessage}/download', [CaseChatController::class, 'download'])->name('cases.chat.download');

    // Client management endpoints for administrators.
    Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
    Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
    Route::get('/clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
    Route::post('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
    Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');
    Route::post('/clients/{client}/attention/{type}', [ClientController::class, 'toggleAttention'])->name('clients.attention');

    // Legal management endpoints for administrators.
    Route::get('/legals', [LegalController::class, 'index'])->name('legals.index');
    Route::post('/legals', [LegalController::class, 'store'])->name('legals.store');
    Route::get('/legals/{legal}/edit', [LegalController::class, 'edit'])->name('legals.edit');
    Route::post('/legals/{legal}', [LegalController::class, 'update'])->name('legals.update');
    Route::post('/legals/{legal}/password', [LegalController::class, 'generatePassword'])->name('legals.password');

    // Activity log listing for administrators.
    Route::get('/logs', [ActivityLogController::class, 'index'])->name('logs.index');
});
