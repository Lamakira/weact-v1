<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Enums\ErrorCodes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ugc\UploadDeliverableRequest;
use App\Http\Resources\DeliverableResource;
use App\Models\Shipment;
use App\Services\Ugc\UgcDeliverableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class UgcDeliverableUploadController extends Controller
{
    public function __construct(
        private readonly UgcDeliverableService $service,
    ) {}

    /**
     * Upload du livrable Unboxing (FR6 étape 5, UGC 4.1) : la Face destinataire
     * dépose sa vidéo pendant que le chrono court. Crée le Deliverable en
     * in_review, fait avancer le tunnel à unboxing_in_review, notifie le
     * Producteur (post-commit). Pas de gate canAccessUgc : une Face engagée
     * poursuit son tunnel même si son abonnement a changé (D-3.3.e reconduite).
     */
    public function store(UploadDeliverableRequest $request, Shipment $shipment): JsonResponse
    {
        Gate::authorize('uploadDeliverable', $shipment);

        $result = $this->service->uploadUnboxing($shipment, $request->file('video'));

        return match ($result['outcome']) {
            'uploaded' => response()->json([
                'data' => new DeliverableResource($result['deliverable']),
                'message' => 'Vidéo Unboxing déposée — en attente de validation du Producteur',
            ], 201),
            'already_uploaded' => response()->json(
                ErrorCodes::AlreadyUploaded->envelope('Votre vidéo Unboxing a déjà été déposée pour ce deal.'),
                422
            ),
            'refund_in_progress' => response()->json(
                ErrorCodes::InvalidStatus->envelope('La commission de ce deal est en cours de remboursement — action impossible.'),
                422
            ),
            default => response()->json(
                ErrorCodes::InvalidStatus->envelope("La vidéo ne peut pas être déposée dans l'état actuel de ce deal."),
                422
            ),
        };
    }
}
