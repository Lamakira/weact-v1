<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Producer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Producer\StoreRatingRequest;
use App\Http\Resources\RatingResource;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Rating;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class RatingController extends Controller
{
    /**
     * Submit a rating for a Face after a completed mission.
     *
     * Creates a rating from the authenticated Producer for the Face
     * associated with the candidature.
     */
    public function store(StoreRatingRequest $request, Candidature $candidature): JsonResponse
    {
        $user = $request->user();

        // Check authorization via RatingPolicy
        if (! Gate::allows('createAsProducer', [Rating::class, $candidature])) {
            // Determine specific error message
            if (! $candidature->canBeRated()) {
                return response()->json([
                    'error' => [
                        'code' => 'RATING_NOT_ALLOWED',
                        'message' => "Les évaluations ne sont possibles qu'après la fin de la mission",
                    ],
                ], 403);
            }

            return response()->json([
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Cette mission ne vous appartient pas',
                ],
            ], 403);
        }

        // Check if user has already rated this Face for this candidature
        if ($candidature->hasRatingFrom($user)) {
            return response()->json([
                'error' => [
                    'code' => 'ALREADY_RATED',
                    'message' => 'Vous avez déjà évalué ce talent pour cette mission',
                ],
            ], 422);
        }

        // Load the face to get the rated entity
        $candidature->loadMissing('face');
        $face = $candidature->face;

        // Create the rating
        $rating = Rating::create([
            'candidature_id' => $candidature->id,
            'rater_id' => $user->id,
            'rated_id' => $face->id,
            'rated_type' => Face::class,
            'score' => $request->validated('score'),
            'comment' => $request->validated('comment'),
        ]);

        // Load relationships for resource
        $rating->load(['rater.userable', 'rated']);

        return response()->json([
            'data' => new RatingResource($rating),
            'message' => 'Évaluation envoyée avec succès',
        ], 201);
    }
}
