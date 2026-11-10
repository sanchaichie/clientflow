<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectMemberController;
use App\Http\Controllers\Api\TaskCommentController;
use App\Http\Controllers\Api\TaskController;
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
    Route::get('/dashboard', DashboardController::class);

    Route::apiResource('users', UserController::class);

    // DELETE on clients/projects archives; restore is admin-only.
    Route::apiResource('clients', ClientController::class);
    Route::post('clients/{client}/restore', [ClientController::class, 'restore'])->name('clients.restore');

    Route::apiResource('projects', ProjectController::class);
    Route::post('projects/{project}/restore', [ProjectController::class, 'restore'])->name('projects.restore');
    Route::post('projects/{project}/members', [ProjectMemberController::class, 'store'])->name('projects.members.store');
    Route::delete('projects/{project}/members/{user}', [ProjectMemberController::class, 'destroy'])->name('projects.members.destroy');

    Route::apiResource('tasks', TaskController::class);
    Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status');

    // Collaboration (Milestone 6)
    Route::get('tasks/{task}/comments', [TaskCommentController::class, 'index'])->name('tasks.comments.index');
    Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('tasks.comments.store');
    Route::delete('comments/{comment}', [TaskCommentController::class, 'destroy'])->name('comments.destroy');

    Route::get('activity', [ActivityLogController::class, 'index'])->name('activity.index');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::patch('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
});
