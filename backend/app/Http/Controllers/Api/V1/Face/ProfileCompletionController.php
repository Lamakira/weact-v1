<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Http\Controllers\Controller;
use App\Models\Face;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileCompletionController extends Controller
{
    /**
     * Get current profile completion status.
     */
    public function show(Request $request): JsonResponse
    {
        $result = $this->getAuthenticatedFace($request);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $face = $result;

        return response()->json([
            'data' => [
                'profile_completion_percentage' => $face->profile_completion_percentage,
                'profile_completion_missing' => $face->profile_completion_missing,
                'profile_completion_is_complete' => $face->profile_completion_is_complete,
            ],
        ]);
    }

    /**
     * Get the authenticated Face from the request.
     */
    private function getAuthenticatedFace(Request $request): Face|JsonResponse
    {
        $user = $request->user();

        if ($user->userable_type !== Face::class) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Accès réservé aux Faces',
                ],
            ], 403);
        }

        return Face::findOrFail($user->userable_id);
    }
}
