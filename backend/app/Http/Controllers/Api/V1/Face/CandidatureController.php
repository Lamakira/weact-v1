<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Enums\MissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Face\StoreCandidatureRequest;
use App\Http\Resources\CandidatureResource;
use App\Models\Candidature;
use App\Models\Mission;
use Illuminate\Http\JsonResponse;

class CandidatureController extends Controller
{
    /**
     * Apply to a mission as a Face.
     *
     * Creates a candidature with status "pending" if:
     * - Mission is published and accepting candidatures
     * - Face hasn't already applied to this mission
     */
    public function store(StoreCandidatureRequest $request, Mission $mission): JsonResponse
    {
        // Check mission is published
        if ($mission->status !== MissionStatus::Published) {
            abort(404);
        }

        // Check mission is accepting candidatures (published + deadline not passed)
        if (! $mission->isAcceptingCandidatures()) {
            return response()->json([
                'error' => [
                    'code' => 'MISSION_CLOSED',
                    'message' => "Cette mission n'accepte plus de candidatures",
                ],
            ], 422);
        }

        // Get Face from authenticated user
        $face = $request->user()->userable;

        // Check if Face has already applied to this mission
        if (Candidature::where('face_id', $face->id)->where('mission_id', $mission->id)->exists()) {
            return response()->json([
                'error' => [
                    'code' => 'ALREADY_APPLIED',
                    'message' => 'Vous avez déjà postulé à cette mission',
                ],
            ], 422);
        }

        // Create the candidature (status defaults to 'pending')
        $candidature = Candidature::create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
            'message_motivation' => $request->validated('message_motivation'),
        ]);

        return response()->json([
            'data' => new CandidatureResource($candidature),
            'message' => 'Candidature envoyée avec succès',
        ], 201);
    }
}
