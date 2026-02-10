<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\AdminController;
use App\Http\Controllers\Api\V1\Admin\ArticleController;
use App\Http\Controllers\Api\V1\Admin\AuthController;
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

    // Admin management routes
    Route::get('/admins', [AdminController::class, 'index'])->name('admin.admins.index');
    Route::post('/admins', [AdminController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('admin.admins.store');

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
