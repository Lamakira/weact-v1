<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Producer;

use App\Enums\DeliverableValidationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\DeliverableAssetResource;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Deliverable;
use App\Models\Producer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Bibliothèque d'assets Producteur (UGC 4.7) : liste des livrables VALIDÉS du
 * Producteur authentifié (Unboxing + Avis), agrégés sur les deux types d'owner.
 * Surface PARALLÈLE additive à l'inbox de validation 5A (D-4.7.b) : l'inbox est
 * une file d'action (transient, in_review), la bibliothèque est une archive
 * (persistante, validated). Calque exact de UgcDeliverableValidationController::index
 * (même garde, même whereHasMorph, même morphWith) MAIS filtre Validated au lieu
 * d'InReview et ordonne par validated_at DESC (plus récent d'abord). Pure couche
 * read — aucune mutation, l'inbox 5A reste INCHANGÉE (AC6).
 */
class ProducerUgcLibraryController extends Controller
{
    /**
     * Liste les livrables validés du Producteur (booking + candidature). Asymétrie
     * FK : booking via users.id, candidature via producers.id (mission.producer_id).
     * Le filtre porte sur le statut du LIVRABLE (Validated), pas sur tunnel_status :
     * un Unboxing validé est téléchargeable même si le deal est encore avis_pending
     * (D-4.7.d). Les URLs média signées (lecture inline + download) sont mintées dans
     * DeliverableAssetResource (réponse Producteur-scopée — la signature EST la garde).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $producer = $user->userable;
        abort_unless($producer instanceof Producer, 403, 'Réservé aux Producteurs.');

        $deliverables = Deliverable::query()
            ->where('validation_status', DeliverableValidationStatus::Validated) // ≠ inbox (InReview)
            ->where(function ($q) use ($user, $producer): void {
                $q->whereHasMorph('owner', [Booking::class], function ($b) use ($user): void {
                    $b->where('producer_id', $user->id);
                })->orWhereHasMorph('owner', [Candidature::class], function ($c) use ($producer): void {
                    $c->whereHas('mission', fn ($m) => $m->where('producer_id', $producer->id));
                });
            })
            ->with(['owner' => function ($morphTo): void {
                $morphTo->morphWith([
                    Booking::class => ['face.userable'],
                    Candidature::class => ['face', 'mission'],
                ]);
            }])
            ->orderByDesc('validated_at')
            ->get();

        return DeliverableAssetResource::collection($deliverables);
    }
}
