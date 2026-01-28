<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Http\Controllers\Controller;
use App\Http\Requests\Face\StoreRatingRequest;
use App\Http\Resources\RatingResource;
use App\Models\Candidature;
use App\Models\Producer;
use App\Models\Rating;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class RatingController extends Controller
{
    /**
     * Submit a rating for a Producer after a completed mission.
     *
     * Creates a rating from the authenticated Face for the Producer
     * associated with the candidature's mission.
     */
    public function store(StoreRatingRequest $request, Candidature $candidature): JsonResponse
    {
        $user = $request->user();

        // Check authorization via RatingPolicy
        if (! Gate::allows('createAsFace', [Rating::class, $candidature])) {
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
                    'message' => 'Cette candidature ne vous appartient pas',
                ],
            ], 403);
        }

        // Check if user has already rated this producer for this candidature
        if ($candidature->hasRatingFrom($user)) {
            return response()->json([
                'error' => [
                    'code' => 'ALREADY_RATED',
                    'message' => 'Vous avez déjà évalué ce producteur pour cette mission',
                ],
            ], 422);
        }

        // Load the mission to get the producer
        $candidature->loadMissing('mission');
        $producer = $candidature->mission->producer;

        // Create the rating
        $rating = Rating::create([
            'candidature_id' => $candidature->id,
            'rater_id' => $user->id,
            'rated_id' => $producer->id,
            'rated_type' => Producer::class,
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
