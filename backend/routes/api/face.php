<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Face\AlbumController;
use App\Http\Controllers\Api\V1\Face\AvailabilityController;
use App\Http\Controllers\Api\V1\Face\BasicInfoController;
use App\Http\Controllers\Api\V1\Face\BioLocationController;
use App\Http\Controllers\Api\V1\Face\CandidatureController;
use App\Http\Controllers\Api\V1\Face\CategoryNicheController;
use App\Http\Controllers\Api\V1\Face\CategoryNicheOptionsController;
use App\Http\Controllers\Api\V1\Face\ConversationController;
use App\Http\Controllers\Api\V1\Face\ExperienceController;
use App\Http\Controllers\Api\V1\Face\FaceDashboardController;
use App\Http\Controllers\Api\V1\Face\FaceVideoController;
use App\Http\Controllers\Api\V1\Face\LanguesController;
use App\Http\Controllers\Api\V1\Face\MessageController;
use App\Http\Controllers\Api\V1\Face\MissionAttendanceController;
use App\Http\Controllers\Api\V1\Face\MissionController;
use App\Http\Controllers\Api\V1\Face\NotificationController;
use App\Http\Controllers\Api\V1\Face\PersonalInfoController;
use App\Http\Controllers\Api\V1\Face\PhysicalCharacteristicsController;
use App\Http\Controllers\Api\V1\Face\PresentationVideoController;
use App\Http\Controllers\Api\V1\Face\ProfileCompletionController;
use App\Http\Controllers\Api\V1\Face\ProfileController;
use App\Http\Controllers\Api\V1\Face\RatingController;
use App\Http\Controllers\Api\V1\Face\SubscriptionPaymentController;
use App\Http\Controllers\Api\V1\Face\SubscriptionStatusController;
use App\Http\Controllers\Api\V1\Face\TarifsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Face API Routes
|--------------------------------------------------------------------------
|
| Routes for authenticated Face users to manage their profile.
|
*/

Route::prefix('v1/face')->middleware(['auth:sanctum', 'api.token'])->group(function () {
    // Dashboard routes (Face only - authorization in controller)
    // Note: Uses controller-level auth instead of 'face' middleware to return
    // proper JSON error format { error: { code, message } } on 403.
    // Same pattern as ProfileCompletionController.
    Route::get('/dashboard/stats', [FaceDashboardController::class, 'stats'])
        ->middleware('throttle:ui-read');
    Route::get('/dashboard/chart-stats', [FaceDashboardController::class, 'chartStats'])
        ->middleware('throttle:ui-read');
    Route::get('/dashboard/available-missions-count', [FaceDashboardController::class, 'availableMissionsCount'])
        ->middleware('throttle:ui-read');
    Route::get('/dashboard/booking-stats', [FaceDashboardController::class, 'bookingStats'])
        ->middleware('throttle:ui-read');
    Route::get('/dashboard/booking-chart-stats', [FaceDashboardController::class, 'bookingChartStats'])
        ->middleware('throttle:ui-read');

    // Basic info routes (nom, prenom, username)
    Route::get('/basic-info', [BasicInfoController::class, 'show'])
        ->middleware('throttle:ui-read');
    Route::put('/basic-info', [BasicInfoController::class, 'update'])
        ->middleware('throttle:60,1');

    // Personal info routes (sexe, date_naissance, nationalite, pays)
    Route::get('/personal-info', [PersonalInfoController::class, 'show'])
        ->middleware('throttle:ui-read');
    Route::put('/personal-info', [PersonalInfoController::class, 'update'])
        ->middleware('throttle:60,1');

    // Profile routes
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])
        ->middleware('throttle:uploads');
    Route::delete('/profile/photo', [ProfileController::class, 'deletePhoto']);

    // Album routes
    Route::get('/album', [AlbumController::class, 'index']);
    Route::post('/album', [AlbumController::class, 'store'])
        ->middleware('throttle:uploads');
    Route::delete('/album/{photo}', [AlbumController::class, 'destroy']);
    Route::put('/album/reorder', [AlbumController::class, 'reorder']);

    // Presentation video routes
    Route::get('/presentation-video', [PresentationVideoController::class, 'show']);
    Route::post('/presentation-video', [PresentationVideoController::class, 'store'])
        ->middleware('throttle:uploads');
    Route::delete('/presentation-video', [PresentationVideoController::class, 'destroy']);

    // Face portfolio video routes (acting + UGC — typed, multi-row)
    Route::get('/videos', [FaceVideoController::class, 'index']);
    Route::post('/videos', [FaceVideoController::class, 'store'])
        ->middleware('throttle:uploads');
    Route::delete('/videos/{video}', [FaceVideoController::class, 'destroy']);

    // Bio and location routes
    Route::get('/bio-location', [BioLocationController::class, 'show'])
        ->middleware('throttle:ui-read');
    Route::put('/bio-location', [BioLocationController::class, 'update'])
        ->middleware('throttle:60,1');

    // Langues routes
    Route::get('/langues', [LanguesController::class, 'show'])
        ->middleware('throttle:ui-read');
    Route::put('/langues', [LanguesController::class, 'update'])
        ->middleware('throttle:60,1');

    // Physical characteristics routes
    Route::get('/physical-characteristics', [PhysicalCharacteristicsController::class, 'show'])
        ->middleware('throttle:ui-read');
    Route::put('/physical-characteristics', [PhysicalCharacteristicsController::class, 'update'])
        ->middleware('throttle:60,1');

    // Category and niche routes
    Route::get('/category-niche', [CategoryNicheController::class, 'show'])
        ->middleware('throttle:ui-read');
    Route::put('/category-niche', [CategoryNicheController::class, 'update'])
        ->middleware('throttle:60,1');

    // Experience routes
    Route::get('/experiences', [ExperienceController::class, 'index'])
        ->middleware('throttle:ui-read');
    Route::post('/experiences', [ExperienceController::class, 'store'])
        ->middleware('throttle:60,1');
    Route::get('/experiences/{experience}', [ExperienceController::class, 'show'])
        ->middleware('throttle:ui-read');
    Route::put('/experiences/{experience}', [ExperienceController::class, 'update'])
        ->middleware('throttle:60,1');
    Route::delete('/experiences/{experience}', [ExperienceController::class, 'destroy'])
        ->middleware('throttle:60,1');

    // Tarifs routes
    Route::get('/tarifs', [TarifsController::class, 'show'])
        ->middleware('throttle:ui-read');
    Route::put('/tarifs', [TarifsController::class, 'update'])
        ->middleware('throttle:60,1');

    // Availability routes
    Route::get('/availability', [AvailabilityController::class, 'show'])
        ->middleware('throttle:ui-read');
    Route::put('/availability', [AvailabilityController::class, 'update'])
        ->middleware('throttle:60,1');

    // Profile completion route
    Route::get('/profile-completion', [ProfileCompletionController::class, 'show'])
        ->middleware('throttle:ui-read');

    // Subscription status route (Face only — premium entitlements + plan CTA)
    Route::get('/subscription-status', [SubscriptionStatusController::class, 'show'])
        ->middleware('throttle:ui-read');

    // Subscription payment initiation (Face only — Fedapay hosted checkout)
    Route::post('/subscription/initiate-payment', [SubscriptionPaymentController::class, 'initiate'])
        ->middleware('throttle:30,1');
    Route::post('/subscription/verify-payment', [SubscriptionPaymentController::class, 'verify'])
        ->middleware('throttle:30,1');
    Route::post('/subscription/cancel-pending', [SubscriptionPaymentController::class, 'cancelPending'])
        ->middleware('throttle:30,1');
    Route::post('/subscription/resume-payment', [SubscriptionPaymentController::class, 'resumePayment'])
        ->middleware('throttle:30,1');

    // Mission routes - browse available missions (Face only)
    Route::get('/missions', [MissionController::class, 'index'])
        ->middleware(['face', 'throttle:60,1']);
    Route::get('/missions/{mission}', [MissionController::class, 'show'])
        ->middleware(['face', 'throttle:60,1']);

    // Candidature routes - apply to missions (Face only, verified email required)
    Route::post('/missions/{mission}/apply', [CandidatureController::class, 'store'])
        ->middleware(['face', 'verified', 'throttle:30,1']);

    // Mission attendance dispute - Face contests being marked absent
    Route::post('/missions/{mission}/dispute-attendance', [MissionAttendanceController::class, 'dispute'])
        ->middleware(['face', 'throttle:30,1']);

    // Candidature routes - view my candidatures (Face only)
    Route::get('/candidatures', [CandidatureController::class, 'index'])
        ->middleware(['face', 'throttle:60,1']);

    // Candidature routes - confirm participation (Face only)
    Route::post('/candidatures/{candidature}/confirm', [CandidatureController::class, 'confirm'])
        ->middleware(['face', 'throttle:30,1']);

    // Candidature routes - cancel pending candidature (Face only)
    Route::post('/candidatures/{candidature}/cancel', [CandidatureController::class, 'cancel'])
        ->middleware(['face', 'throttle:30,1']);

    // Rating routes - rate Producer after completed mission (Face only)
    Route::post('/candidatures/{candidature}/rate', [RatingController::class, 'store'])
        ->middleware(['face', 'throttle:30,1']);

    // Notification routes (Face only)
    Route::get('/notifications', [NotificationController::class, 'index'])
        ->middleware(['face', 'throttle:60,1']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])
        ->middleware(['face', 'throttle:polling']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
        ->middleware(['face', 'throttle:60,1']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])
        ->middleware(['face', 'throttle:30,1']);

    // Conversation routes (Face only)
    Route::get('/conversations', [ConversationController::class, 'index'])
        ->middleware(['face', 'throttle:60,1']);
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])
        ->middleware(['face', 'throttle:60,1']);
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])
        ->middleware(['face', 'throttle:30,1']);
});

/*
|--------------------------------------------------------------------------
| Public Face API Routes
|--------------------------------------------------------------------------
|
| Routes that do not require authentication.
|
*/

Route::prefix('v1/face')->group(function () {
    // Category and niche options (public - for dropdown population)
    Route::get('/options/categories', [CategoryNicheOptionsController::class, 'categories'])
        ->middleware('throttle:60,1');
    Route::get('/options/niches', [CategoryNicheOptionsController::class, 'niches'])
        ->middleware('throttle:60,1');
});
