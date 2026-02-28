<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Producer;

use App\Http\Controllers\Controller;
use App\Http\Resources\FaceResource;
use App\Models\Face;
use App\Models\Producer;
use Illuminate\Http\Request;

class FaceController extends Controller
{
    /**
     * Display a Face's full profile.
     *
     * Any authenticated Producer can view any Face's profile
     * (needed for direct bookings outside of mission candidatures).
     */
    public function show(Request $request, Face $face): FaceResource
    {
        $user = $request->user();

        // Verify user is a Producer
        if ($user->userable_type !== Producer::class) {
            abort(403, 'Accès réservé aux Producteurs');
        }

        // Load relationships for full profile
        $face->load(['photos', 'experiences']);

        return new FaceResource($face);
    }
}
