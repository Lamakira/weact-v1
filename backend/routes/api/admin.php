<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\AdminController;
use App\Http\Controllers\Api\V1\Admin\AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\ArticleController;
use App\Http\Controllers\Api\V1\Admin\AuthController;
use App\Http\Controllers\Api\V1\Admin\FaceController;
use App\Http\Controllers\Api\V1\Admin\MissionController;
use App\Http\Controllers\Api\V1\Admin\ProducerController;
use Illuminate\Support\Facades\Route;

// Public admin routes (no auth)
Route::prefix('v1/admin')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('admin.login');
});

// Protected admin routes
Route::prefix('v1/admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout'])->name('admin.logout');
    Route::get('/me', [AuthController::class, 'me'])->name('admin.me');

    // Dashboard stats route
    Route::get('/dashboard/stats', [AdminDashboardController::class, 'stats'])
        ->middleware('throttle:30,1')
        ->name('admin.dashboard.stats');

    // Admin management routes (superadmin only)
    Route::middleware('superadmin')->group(function () {
        Route::get('/admins', [AdminController::class, 'index'])->name('admin.admins.index');
        Route::post('/admins', [AdminController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('admin.admins.store');
        Route::get('/admins/{admin}', [AdminController::class, 'show'])->name('admin.admins.show');
        Route::put('/admins/{admin}', [AdminController::class, 'update'])
            ->middleware('throttle:30,1')
            ->name('admin.admins.update');
        Route::delete('/admins/{admin}', [AdminController::class, 'destroy'])
            ->middleware('throttle:30,1')
            ->name('admin.admins.destroy');
    });

    // Face management routes
    Route::get('/faces', [FaceController::class, 'index'])->name('admin.faces.index');
    Route::get('/faces/{face}', [FaceController::class, 'show'])->name('admin.faces.show');
    Route::put('/faces/{face}', [FaceController::class, 'update'])
        ->middleware('throttle:30,1')
        ->name('admin.faces.update');
    Route::patch('/faces/{face}/toggle-active', [FaceController::class, 'toggleActive'])
        ->middleware('throttle:30,1')
        ->name('admin.faces.toggle-active');
    Route::delete('/faces/{face}', [FaceController::class, 'destroy'])
        ->middleware('throttle:30,1')
        ->name('admin.faces.destroy');

    // Producer management routes
    Route::get('/producers', [ProducerController::class, 'index'])->name('admin.producers.index');
    Route::get('/producers/{producer}', [ProducerController::class, 'show'])->name('admin.producers.show');
    Route::put('/producers/{producer}', [ProducerController::class, 'update'])
        ->middleware('throttle:30,1')
        ->name('admin.producers.update');
    Route::patch('/producers/{producer}/toggle-active', [ProducerController::class, 'toggleActive'])
        ->middleware('throttle:30,1')
        ->name('admin.producers.toggle-active');
    Route::delete('/producers/{producer}', [ProducerController::class, 'destroy'])
        ->middleware('throttle:30,1')
        ->name('admin.producers.destroy');

    // Mission management routes (read-only)
    Route::get('/missions', [MissionController::class, 'index'])->name('admin.missions.index');
    Route::get('/missions/{mission}', [MissionController::class, 'show'])->name('admin.missions.show');

    // Article management routes
    Route::get('/articles', [ArticleController::class, 'index'])->name('admin.articles.index');

    Route::post('/articles', [ArticleController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('admin.articles.store');

    Route::put('/articles/{article}', [ArticleController::class, 'update'])
        ->middleware('throttle:30,1')
        ->name('admin.articles.update');

    Route::delete('/articles/{article}', [ArticleController::class, 'destroy'])
        ->middleware('throttle:30,1')
        ->name('admin.articles.destroy');

    Route::patch('/articles/{article}/category', [ArticleController::class, 'updateCategory'])
        ->middleware('throttle:30,1')
        ->name('admin.articles.update-category');

    Route::patch('/articles/{article}/status', [ArticleController::class, 'updateStatus'])
        ->middleware('throttle:30,1')
        ->name('admin.articles.update-status');
});
