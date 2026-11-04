<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ImageContractController;
use Illuminate\Support\Facades\Route;

// Autorisation d'utilisation de l'image : 404 tant que IMAGE_CONTRACTS_ENABLED est désactivé.
Route::prefix('v1')->middleware(['auth:sanctum', 'api.token', 'face_or_producer', 'throttle:10,1'])->group(function (): void {
    Route::get('/image-contract/template', [ImageContractController::class, 'template'])
        ->name('image-contract.template');

    Route::post('/image-contract/code', [ImageContractController::class, 'sendCode'])
        ->name('image-contract.code');
});
