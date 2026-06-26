<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Enums\ErrorCodes;
use App\Http\Controllers\Controller;
use App\Http\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Services\Ugc\UgcShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class UgcEngagementController extends Controller
{
    public function __construct(
        private readonly UgcShipmentService $shipmentService,
    ) {}

    /**
     * « Produit reçu » (FR6 étape 4, story 3.3) : la Face confirme la
     * réception — recu_le est figé, le tunnel passe `received`, le chrono
     * Unboxing démarre (deadline dérivée, D-3.3.a). Pas de gate canAccessUgc :
     * une Face engagée continue son tunnel (D-3.3.e).
     */
    public function confirmReceipt(Shipment $shipment): JsonResponse
    {
        Gate::authorize('confirmReceipt', $shipment);

        $result = $this->shipmentService->markReceived($shipment);

        return match ($result['outcome']) {
            'received' => response()->json([
                'data' => new ShipmentResource($result['shipment']),
                'message' => 'Réception confirmée — le chrono Unboxing démarre',
            ]),
            'already' => response()->json(
                ErrorCodes::AlreadyReceived->envelope('La réception de ce produit a déjà été confirmée.'),
                422
            ),
            'refund_in_progress' => response()->json(
                ErrorCodes::InvalidStatus->envelope('La commission de ce deal est en cours de remboursement — action impossible.'),
                422
            ),
            default => response()->json(
                ErrorCodes::InvalidStatus->envelope("La réception ne peut pas être confirmée dans l'état actuel de ce deal."),
                422
            ),
        };
    }
}
