<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Http\Controllers\Controller;
use App\Http\Resources\FaceDashboardStatsResource;
use App\Models\Candidature;
use App\Models\Face;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaceDashboardController extends Controller
{
    /**
     * Get dashboard statistics for the authenticated Face.
     *
     * Returns candidature counts grouped by status:
     * - pending: Candidatures waiting for producer response
     * - accepted: Candidatures accepted, waiting for Face confirmation
     * - in_progress: Confirmed candidatures (active missions)
     * - completed: Finished missions
     *
     * Note: Rejected candidatures are excluded from the stats.
     */
    public function stats(Request $request): JsonResponse
    {
        $result = $this->getAuthenticatedFace($request);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $face = $result;

        // Get candidature counts by status
        $statusCounts = Candidature::where('face_id', $face->id)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return response()->json([
            'data' => new FaceDashboardStatsResource($statusCounts),
            'message' => 'Dashboard stats retrieved successfully',
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
