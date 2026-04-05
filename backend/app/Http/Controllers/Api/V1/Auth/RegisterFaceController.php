<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterFaceRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\FaceRegistrationService;
use Illuminate\Http\JsonResponse;

class RegisterFaceController extends Controller
{
    public function __construct(
        private readonly FaceRegistrationService $registrationService
    ) {}

    /**
     * Handle Face registration.
     */
    public function __invoke(RegisterFaceRequest $request): JsonResponse
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
