<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\AdminAttendanceDisputeController;
use App\Http\Controllers\Api\V1\Admin\AdminController;
use App\Http\Controllers\Api\V1\Admin\AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\AdminEngagementController;
use App\Http\Controllers\Api\V1\Admin\AdminFaceSubscriptionController;
use App\Http\Controllers\Api\V1\Admin\AdminFinanceController;
use App\Http\Controllers\Api\V1\Admin\AdminForgotPasswordController;
use App\Http\Controllers\Api\V1\Admin\AdminResetPasswordController;
use App\Http\Controllers\Api\V1\Admin\AdminUgcSuspensionController;
use App\Http\Controllers\Api\V1\Admin\ArticleController;
use App\Http\Controllers\Api\V1\Admin\AuthController;
use App\Http\Controllers\Api\V1\Admin\FaceController;
use App\Http\Controllers\Api\V1\Admin\MissionController;
use App\Http\Controllers\Api\V1\Admin\ProducerController;
use App\Http\Controllers\Api\V1\Admin\WithdrawalRequestController;
use Illuminate\Support\Facades\Route;

// Public admin routes (no auth)
Route::prefix('v1/admin')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('admin.login');

    Route::post('/forgot-password', AdminForgotPasswordController::class)
        ->middleware('throttle:5,1')
        ->name('admin.forgot-password');

    Route::post('/reset-password', AdminResetPasswordController::class)
        ->middleware('throttle:5,1')
        ->name('admin.reset-password');
});

// Protected admin routes
Route::prefix('v1/admin')->middleware(['auth:sanctum', 'api.token', 'admin'])->group(function () {
    // Auth routes (all admin roles)
    Route::post('/logout', [AuthController::class, 'logout'])->name('admin.logout');
    Route::get('/me', [AuthController::class, 'me'])->name('admin.me');

    // Article management routes (all admin roles - editors manage articles)
    Route::get('/articles', [ArticleController::class, 'index'])
        ->middleware('throttle:30,1')
        ->name('admin.articles.index');

    Route::get('/articles/{article}', [ArticleController::class, 'show'])
        ->name('admin.articles.show');

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

    // Admin + SuperAdmin only (editor blocked)
    Route::middleware('admin.role:superadmin,admin')->group(function () {
        // Dashboard routes
        Route::get('/dashboard/stats', [AdminDashboardController::class, 'stats'])
            ->middleware('throttle:30,1')
            ->name('admin.dashboard.stats');
        Route::get('/dashboard/trends', [AdminDashboardController::class, 'trends'])
            ->middleware('throttle:30,1')
            ->name('admin.dashboard.trends');
        Route::get('/dashboard/recent-activity', [AdminDashboardController::class, 'recentActivity'])
            ->middleware('throttle:30,1')
            ->name('admin.dashboard.recent-activity');

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

        // Engagements — unified "Faces à contacter" monitoring (read-only)
        Route::get('/engagements', [AdminEngagementController::class, 'index'])
            ->middleware('throttle:30,1')
            ->name('admin.engagements.index');

        // Finance routes (read-only)
        Route::get('/finance/overview', [AdminFinanceController::class, 'overview'])
            ->middleware('throttle:30,1')
            ->name('admin.finance.overview');
        Route::get('/finance/recent-withdrawals', [AdminFinanceController::class, 'recentWithdrawals'])
            ->middleware('throttle:30,1')
            ->name('admin.finance.recent-withdrawals');
        Route::get('/finance/withdrawal-requests', [WithdrawalRequestController::class, 'index'])
            ->middleware('throttle:30,1')
            ->name('admin.finance.withdrawal-requests.index');
        Route::post('/finance/withdrawal-requests/{withdrawalRequest}/approve', [WithdrawalRequestController::class, 'approve'])
            ->middleware('throttle:30,1')
            ->name('admin.finance.withdrawal-requests.approve');
        Route::post('/finance/withdrawal-requests/{withdrawalRequest}/reject', [WithdrawalRequestController::class, 'reject'])
            ->middleware('throttle:30,1')
            ->name('admin.finance.withdrawal-requests.reject');

        // Mission attendance dispute resolution (FIX-26.8)
        Route::get('/attendance-disputes', [AdminAttendanceDisputeController::class, 'index'])
            ->middleware('throttle:30,1')
            ->name('admin.attendance-disputes.index');
        Route::post('/attendance-disputes/{entry}/resolve', [AdminAttendanceDisputeController::class, 'resolve'])
            ->middleware('throttle:30,1')
            ->name('admin.attendance-disputes.resolve');

        // UGC soft-suspension appeals review + reactivation (épic 5, story 5.3)
        Route::get('/ugc/suspensions', [AdminUgcSuspensionController::class, 'index'])
            ->middleware('throttle:30,1')
            ->name('admin.ugc-suspensions.index');
        Route::post('/ugc/suspensions/{ugcSuspension}/reactivate', [AdminUgcSuspensionController::class, 'reactivate'])
            ->middleware('throttle:30,1')
            ->name('admin.ugc-suspensions.reactivate');
        Route::post('/ugc/suspensions/{ugcSuspension}/reject-appeal', [AdminUgcSuspensionController::class, 'rejectAppeal'])
            ->middleware('throttle:30,1')
            ->name('admin.ugc-suspensions.reject-appeal');

        // Subscriptions back-office — cross-Face read-only list + KPIs.
        // ⚠️ `stats` is declared BEFORE any `{subscription}` binding route to
        // avoid the literal segment colliding with the route-model binding.
        Route::get('/face-subscriptions', [AdminFaceSubscriptionController::class, 'list'])
            ->middleware('throttle:30,1')
            ->name('admin.face-subscriptions.list');
        Route::get('/face-subscriptions/stats', [AdminFaceSubscriptionController::class, 'stats'])
            ->middleware('throttle:30,1')
            ->name('admin.face-subscriptions.stats');

        // Face subscription operations (FEATURE-FP-1.4)
        Route::get('/faces/{face}/subscriptions', [AdminFaceSubscriptionController::class, 'index'])
            ->middleware('throttle:30,1')
            ->name('admin.faces.subscriptions.index');
        Route::post('/faces/{face}/subscriptions/activate', [AdminFaceSubscriptionController::class, 'activate'])
            ->middleware('throttle:30,1')
            ->name('admin.faces.subscriptions.activate');
        Route::post('/face-subscriptions/{subscription}/extend', [AdminFaceSubscriptionController::class, 'extend'])
            ->middleware('throttle:30,1')
            ->name('admin.face-subscriptions.extend');
        Route::post('/face-subscriptions/{subscription}/cancel', [AdminFaceSubscriptionController::class, 'cancel'])
            ->middleware('throttle:30,1')
            ->name('admin.face-subscriptions.cancel');
        Route::post('/face-subscriptions/{subscription}/correct', [AdminFaceSubscriptionController::class, 'correct'])
            ->middleware('throttle:30,1')
            ->name('admin.face-subscriptions.correct');
        Route::post('/face-subscriptions/{subscription}/change-tier', [AdminFaceSubscriptionController::class, 'changeTier'])
            ->middleware('throttle:30,1')
            ->name('admin.face-subscriptions.change-tier');
    });

    // SuperAdmin only
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

        Route::post('/admins/{admin}/send-reset-link', [AdminController::class, 'sendPasswordReset'])
            ->middleware('throttle:30,1')
            ->name('admin.admins.send-reset-link');
    });
});
