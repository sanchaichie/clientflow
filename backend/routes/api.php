<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
| All routes here are prefixed with /api. Requests from the SPA origin
| (SANCTUM_STATEFUL_DOMAINS) get session + CSRF handling via statefulApi().
*/

// Public auth endpoints. Login has its own per-email+IP throttle (LoginRequest);
// the reset endpoints get a coarse per-IP limit against abuse.
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('throttle:6,1')->group(function () {
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink']);
    Route::post('/reset-password', [PasswordResetController::class, 'reset']);
});

// Everything else requires an authenticated, active user.
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::apiResource('users', UserController::class);
});
