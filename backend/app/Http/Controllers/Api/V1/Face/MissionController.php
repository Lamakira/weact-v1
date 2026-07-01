<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Enums\ErrorCodes;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mission\FilterMissionsRequest;
use App\Http\Resources\CandidatureResource;
use App\Http\Resources\MissionResource;
use App\Models\Candidature;
use App\Models\Mission;
use App\Services\FaceEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MissionController extends Controller
{
    public function __construct(
        private readonly FaceEntitlementService $entitlement,
    ) {}

    /**
     * Display a specific published mission available for Faces.
     * Returns 404 if mission doesn't exist or is not published.
     * Also returns the Face's candidature for this mission if they have applied.
     */
    public function show(Request $request, Mission $mission): JsonResponse
    {
        $mission->load('producer');

        $face = $request->user()->userable;
        $candidature = Candidature::where('face_id', $face->id)
            ->where('mission_id', $mission->id)
            ->first();

        // Allow viewing if mission is published OR if the face has a candidature on it
        if ($mission->status !== MissionStatus::Published && ! $candidature) {
            abort(404);
        }

        // is_active (3.0) : mission d'un producteur désactivé invisible — sauf
        // candidature existante (suivi d'engagement, parité exception ci-dessus).
        if (! $candidature && ! $mission->hasActiveProducer()) {
            abort(404);
        }

        // Gate UGC (FR5) : détail réservé aux Faces abonnées, sauf candidature existante
        if ($mission->type_mission === MissionType::Ugc
            && ! $candidature
            && ! $this->entitlement->canAccessUgc($face)) {
            return response()->json(
                ErrorCodes::UgcSubscriptionRequired->envelope(
                    "L'accès aux missions UGC est réservé aux Faces abonnées (Starter et plus)."
                ),
                403
            );
        }

        // 3.3 (AC8) : la Face engagée voit son tracking — CandidatureResource
        // expose `shipment` via whenLoaded depuis 3.1, il suffit de le charger.
        $candidature?->loadMissing(['shipment', 'deliverables']);

        return response()->json([
            'data' => new MissionResource($mission),
            'candidature' => $candidature ? new CandidatureResource($candidature) : null,
        ]);
    }

    /**
     * Display a paginated listing of published missions available for Faces.
     * Shows only published missions, ordered by most recent first.
     * Paginated with 12 missions per page.
     * Supports optional filtering by lieu, budget, date_tournage, and type_mission.
     */
    public function index(FilterMissionsRequest $request): JsonResponse
    {
        $missions = Mission::where('status', MissionStatus::Published)
            ->where('type_mission', '!=', MissionType::Ugc->value)
            ->whereProducerActive()
            ->notExpired() // exclut les missions obsolètes (date limite candidature / tournage passée)
            ->when($request->lieu, function ($q, $lieu) {
                // Escape LIKE wildcards to prevent pattern injection
                $escaped = str_replace(['%', '_'], ['\%', '\_'], $lieu);

                return $q->where('lieu', 'like', "%{$escaped}%");
            })
            ->when($request->budget_min, fn ($q, $min) => $q->where('budget', '>=', $min))
            ->when($request->budget_max, fn ($q, $max) => $q->where('budget', '<=', $max))
            ->when($request->date_tournage, fn ($q, $date) => $q->where('date_tournage', '>=', $date))
            ->when($request->type_mission, fn ($q, $type) => $q->where('type_mission', $type))
            ->with('producer')
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        $response = MissionResource::collection($missions)->response()->getData(true);

        // Add message for empty state
        if ($missions->isEmpty()) {
            // Different message when filters are applied
            $hasFilters = $request->lieu || $request->budget_min || $request->budget_max
                || $request->date_tournage || $request->type_mission;

            $response['message'] = $hasFilters
                ? 'Aucune mission ne correspond à vos critères'
                : 'Aucune mission disponible pour le moment';
        }

        return response()->json($response);
    }
}
