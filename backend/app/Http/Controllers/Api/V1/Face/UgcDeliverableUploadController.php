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
     * Upload d'un livrable Unboxing OU Avis (FR6 étape 5, UGC 4.1 + 4.3) : la
     * Face destinataire dépose sa vidéo pendant que le chrono court (+ re-upload
     * après reject/retouche). Le kind ciblé et la fenêtre sont dérivés serveur du
     * tunnel_status (received → unboxing ; avis_pending → avis). Crée/maj le
     * Deliverable en in_review, fait avancer le tunnel à *_in_review, notifie le
     * Producteur (post-commit). Pas de gate canAccessUgc : une Face engagée
     * poursuit son tunnel même si son abonnement a changé (D-3.3.e reconduite).
     */
    public function store(UploadDeliverableRequest $request, Shipment $shipment): JsonResponse
    {
        Gate::authorize('uploadDeliverable', $shipment);

        $result = $this->service->upload($shipment, $request->file('video'));

        return match ($result['outcome']) {
            'uploaded' => response()->json([
                'data' => new DeliverableResource($result['deliverable']),
                // Message kind-aware : « Unboxing » (4.1) ou « Avis » (4.3). Le
                // libellé exact « Vidéo Unboxing déposée … » reste pinné par le
                // test 4.1 via kind->label().
                'message' => "Vidéo {$result['deliverable']->kind->label()} déposée — en attente de validation du Producteur",
            ], 201),
            'already_uploaded' => response()->json(
                // Générique : sur ce chemin le kind n'est pas toujours connu
                // (idempotence lue sur le tunnel avant résolution du kind).
                ErrorCodes::AlreadyUploaded->envelope('Cette vidéo a déjà été déposée pour ce deal.'),
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
