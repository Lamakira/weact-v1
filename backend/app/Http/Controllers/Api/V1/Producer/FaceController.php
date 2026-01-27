<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Producer;

use App\Http\Controllers\Controller;
use App\Http\Resources\FaceResource;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Producer;
use Illuminate\Http\Request;

class FaceController extends Controller
{
    /**
     * Display a candidate's full profile.
     *
     * Producers can only view profiles of Faces who have applied
     * to at least one of their missions.
     */
    public function show(Request $request, Face $face): FaceResource
    {
        $user = $request->user();

        // Verify user is a Producer
        if ($user->userable_type !== Producer::class) {
            abort(403, 'Accès réservé aux Producteurs');
        }

        $producer = $user->userable;

        // Check if this Face has applied to at least one of this Producer's missions
        $hasApplied = Candidature::whereHas('mission', function ($query) use ($producer) {
            $query->where('producer_id', $producer->id);
        })
            ->where('face_id', $face->id)
            ->exists();

        if (! $hasApplied) {
            abort(403, 'Vous ne pouvez consulter que les profils des candidats ayant postulé à vos missions');
        }

        // Load relationships for full profile
        $face->load(['photos', 'experiences']);

        return new FaceResource($face);
    }
}
