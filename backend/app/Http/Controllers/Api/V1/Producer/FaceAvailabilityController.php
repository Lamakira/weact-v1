<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Producer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Producer\ShowFaceAvailabilityRequest;
use App\Models\Face;
use App\Models\User;
use App\Services\Agenda\FaceAgendaService;
use Illuminate\Http\JsonResponse;

class FaceAvailabilityController extends Controller
{
    public function __construct(private readonly FaceAgendaService $agenda) {}

    /**
     * Jours « Indisponible » d'une Face, sans motif : mêmes règles d'accès que Producer\FaceController::show
     * (Producteur connecté ; Face désactivée introuvable). Le rôle est vérifié par la FormRequest.
     */
    public function show(ShowFaceAvailabilityRequest $request, Face $face): JsonResponse
    {
        /** @var User|null $faceUser */
        $faceUser = $face->user()->where('is_active', true)->first();

        abort_unless($faceUser !== null, 404);

        return response()->json([
            'data' => $this->agenda->producerView($faceUser, $face, (string) $request->query('from'), (string) $request->query('to')),
        ]);
    }
}
