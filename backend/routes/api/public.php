<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Public\ProducerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
|
| Routes for unauthenticated public access.
| No authentication middleware applied.
|
*/

Route::prefix('v1/public')->middleware('throttle:60,1')->group(function () {
    // Producer public profile
    Route::get('/producers/{id}', [ProducerController::class, 'show'])
        ->whereNumber('id');
});
