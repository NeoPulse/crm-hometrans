<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Public route serving the login form for guests.
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'authenticate'])->name('login.process');
});

// Protected routes that require an authenticated session.
Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        // Render the secured dashboard landing page for authenticated staff.
        return view('home');
    })->name('home');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
