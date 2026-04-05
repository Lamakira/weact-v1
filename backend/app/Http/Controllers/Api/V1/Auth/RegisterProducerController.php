<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterProducerRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\ProducerRegistrationService;
use Illuminate\Http\JsonResponse;

class RegisterProducerController extends Controller
{
    public function __construct(
        private readonly ProducerRegistrationService $registrationService
    ) {}

    /**
     * Handle Producer registration.
     */
    public function __invoke(RegisterProducerRequest $request): JsonResponse
    {
        $result = $this->registrationService->register($request->validated(), $request->ip());

        return response()->json([
            'data' => [
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
            ],
            'message' => 'Inscription réussie',
        ], 201);
    }
}
