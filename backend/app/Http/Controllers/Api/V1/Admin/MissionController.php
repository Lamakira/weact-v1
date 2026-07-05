<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\MissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MissionResource;
use App\Models\Mission;
use App\Support\Sql;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MissionController extends Controller
{
    /**
     * List all missions with pagination, search, and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Mission::with('producer.user')
            ->withCount('candidatures')
            ->orderBy('created_at', 'desc');

        // Search by titre, lieu, or producer name/email
        if ($request->filled('search') && is_string($request->query('search'))) {
            $search = Sql::escapeLike($request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('titre', 'like', "%{$search}%")
                    ->orWhere('lieu', 'like', "%{$search}%")
                    ->orWhereHas('producer', function ($pq) use ($search) {
                        $pq->where('agency_name', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhereHas('user', function ($uq) use ($search) {
                                $uq->where('email', 'like', "%{$search}%");
                            });
                    });
            });
        }

        // Filter by status
        if ($request->filled('status') && is_string($request->query('status'))) {
            $status = MissionStatus::tryFrom($request->query('status'));
            if ($status) {
                $query->status($status);
            }
        }

        // Filter by type_mission
        if ($request->filled('type_mission') && is_string($request->query('type_mission'))) {
            $query->where('type_mission', $request->query('type_mission'));
        }

        $missions = $query->paginate(15);

        return response()->json([
            'data' => MissionResource::collection($missions),
            'meta' => [
                'current_page' => $missions->currentPage(),
                'last_page' => $missions->lastPage(),
                'per_page' => $missions->perPage(),
                'total' => $missions->total(),
            ],
        ]);
    }

    /**
     * Show a single mission with full detail.
     */
    public function show(Mission $mission): JsonResponse
    {
        $mission->load('producer.user');
        $mission->loadCount('candidatures');

        return response()->json([
            'data' => new MissionResource($mission),
            'message' => 'Mission récupérée avec succès',
        ]);
    }
}
