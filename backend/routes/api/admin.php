<?php

declare(strict_types=1);

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
    Route::post('/articles', [ArticleController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('admin.articles.store');

    Route::patch('/articles/{article}/category', [ArticleController::class, 'updateCategory'])
        ->middleware('throttle:30,1')
        ->name('admin.articles.update-category');
});
