<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Producer;

use App\Enums\ErrorCodes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ugc\ConfirmShipmentRequest;
use App\Http\Resources\ShipmentResource;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Shipment;
use App\Services\Ugc\UgcShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class UgcShipmentController extends Controller
{
    public function __construct(
        private readonly UgcShipmentService $shipmentService,
    ) {}

    /**
     * Confirme l'expédition d'un booking UGC accepté (tunnel étape 3, AR9).
     */
    public function confirmForBooking(ConfirmShipmentRequest $request, Booking $booking): JsonResponse
    {
        Gate::authorize('create', [Shipment::class, $booking]);

        return $this->respond($this->shipmentService->confirm($booking, $request->validated()));
    }

    /**
     * Confirme l'expédition vers une Face engagée (candidature confirmée) d'une mission UGC.
     */
    public function confirmForCandidature(ConfirmShipmentRequest $request, Candidature $candidature): JsonResponse
    {
        Gate::authorize('create', [Shipment::class, $candidature]);

        return $this->respond($this->shipmentService->confirm($candidature, $request->validated()));
    }

    /**
     * @param  array{outcome: string, shipment?: \App\Models\Shipment}  $result
     */
    private function respond(array $result): JsonResponse
    {
        return match ($result['outcome']) {
            'confirmed' => response()->json([
                'data' => new ShipmentResource($result['shipment']),
                'message' => 'Expédition confirmée',
            ], 201),
            'already' => response()->json(
                ErrorCodes::AlreadyShipped->envelope("L'expédition de ce deal a déjà été confirmée."),
                422
            ),
            'refund_in_progress' => response()->json(
                ErrorCodes::InvalidStatus->envelope('La commission de ce deal est en cours de remboursement — expédition impossible.'),
                422
            ),
            default => response()->json(
                ErrorCodes::InvalidStatus->envelope('Ce deal ne peut pas être expédié dans son état actuel.'),
                422
            ),
        };
    }
}
