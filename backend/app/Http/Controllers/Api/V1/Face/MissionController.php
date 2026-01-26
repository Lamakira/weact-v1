<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Enums\MissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MissionResource;
use App\Models\Mission;
use Illuminate\Http\JsonResponse;

class MissionController extends Controller
{
    /**
     * Display a paginated listing of published missions available for Faces.
     * Shows only published missions, ordered by most recent first.
     * Paginated with 12 missions per page.
     */
    public function index(): JsonResponse
    {
        $missions = Mission::where('status', MissionStatus::Published)
            ->with('producer')
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        $response = MissionResource::collection($missions)->response()->getData(true);

        // Add message for empty state
        if ($missions->isEmpty()) {
            $response['message'] = 'Aucune mission disponible pour le moment';
        }

        return response()->json($response);
    }
}
