<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Http\Controllers\Controller;
use App\Http\Requests\Face\ShowAgendaRequest;
use App\Http\Requests\Face\UpdateUnavailableDaysRequest;
use App\Models\Face;
use App\Models\User;
use App\Services\Agenda\FaceAgendaService;
use Illuminate\Http\JsonResponse;

/**
 * Calendrier de la Face connectée (l'accès Face est vérifié par les FormRequests).
 */
class AgendaController extends Controller
{
    public function __construct(private readonly FaceAgendaService $agenda) {}

    public function index(ShowAgendaRequest $request): JsonResponse
    {
        [$user, $face] = $this->actor($request->user());

        return response()->json([
            'data' => $this->agenda->agenda($user, $face, (string) $request->query('from'), (string) $request->query('to')),
        ]);
    }

    public function updateUnavailableDays(UpdateUnavailableDaysRequest $request): JsonResponse
    {
        [$user, $face] = $this->actor($request->user());

        $days = $this->agenda->updateUnavailableDays($user, $face, $request->days('add'), $request->days('remove'));

        return response()->json([
            'data' => ['unavailable_days' => $days],
            'message' => 'Agenda mis à jour',
        ]);
    }

    /**
     * @return array{0: User, 1: Face}
     */
    private function actor(?User $user): array
    {
        abort_unless($user !== null, 401);

        return [$user, Face::query()->findOrFail($user->userable_id)];
    }
}
