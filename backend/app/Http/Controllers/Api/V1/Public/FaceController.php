<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Public\ListFacesRequest;
use App\Http\Resources\PublicFaceResource;
use App\Models\Face;
use Illuminate\Http\JsonResponse;

class FaceController extends Controller
{
    /**
     * Display a paginated list of public Faces.
     *
     * No authentication required - this is a PUBLIC endpoint.
     */
    public function index(ListFacesRequest $request): JsonResponse
    {
        // Get validated per_page from form request
        $perPage = $request->getPerPage();

        // Query faces - paginate all faces for MVP
        // In the future, we might filter by is_available or profile completion
        $faces = Face::query()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => PublicFaceResource::collection($faces),
            'meta' => [
                'current_page' => $faces->currentPage(),
                'last_page' => $faces->lastPage(),
                'per_page' => $faces->perPage(),
                'total' => $faces->total(),
            ],
            'message' => 'Faces retrieved successfully',
        ]);
    }
}
