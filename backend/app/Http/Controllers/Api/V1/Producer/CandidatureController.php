<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Producer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Producer\IndexMissionCandidaturesRequest;
use App\Http\Resources\ProducerCandidatureResource;
use App\Models\Candidature;
use App\Models\Mission;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CandidatureController extends Controller
{
    /**
     * List all candidatures for a specific mission.
     *
     * Returns paginated candidatures with face details.
     * Only the mission owner (Producer) can access this endpoint.
     * Supports optional status filter via query parameter.
     */
    public function index(IndexMissionCandidaturesRequest $request, Mission $mission): AnonymousResourceCollection
    {
        $user = $request->user();
        $producer = $user->userable;

        // Authorization: mission must belong to this Producer
        if ($mission->producer_id !== $producer->id) {
            abort(403, 'Cette mission ne vous appartient pas');
        }

        $query = Candidature::where('mission_id', $mission->id)
            ->with(['face'])
            ->latest();

        // Apply status filter if provided
        $statusFilter = $request->getStatusFilter();
        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        $candidatures = $query->paginate(15);

        return ProducerCandidatureResource::collection($candidatures);
    }
}
