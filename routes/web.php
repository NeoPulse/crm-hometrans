<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CaseManagerController;
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

        // Admins default to the case manager overview.
        return redirect()->route('casemanager.index');
    })->name('home');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Case manager endpoints for admin and legal users.
    Route::get('/casemanager', [CaseManagerController::class, 'index'])->name('casemanager.index');
    Route::post('/casemanager', [CaseManagerController::class, 'store'])->name('casemanager.store');
    Route::get('/casemanager/legal', [CaseManagerController::class, 'legalIndex'])->name('casemanager.legal');
    Route::get('/casemanager/{caseFile}/edit', [CaseManagerController::class, 'edit'])->name('casemanager.edit');
    Route::post('/casemanager/{caseFile}/participants', [CaseManagerController::class, 'updateParticipants'])->name('casemanager.participants');
    Route::post('/casemanager/{caseFile}/details', [CaseManagerController::class, 'updateDetails'])->name('casemanager.details');
    Route::post('/casemanager/{caseFile}/attention/{type}', [CaseManagerController::class, 'toggleAttention'])->name('casemanager.attention');
    Route::get('/casemanager/user-search', [CaseManagerController::class, 'searchUsers'])->name('casemanager.usersearch');
});
