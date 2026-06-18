<?php

declare(strict_types=1);

namespace App\Services\Ugc;

use App\Enums\UgcSuspensionAppealStatus;
use App\Enums\UgcSuspensionReason;
use App\Enums\UgcTunnelStatus;
use App\Events\FaceUgcSuspended;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Shipment;
use App\Models\UgcSuspension;
use App\Models\User;
use App\Services\EscrowService;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Suspension douce UGC (épic 5, story 5.1) : à progress >= 1.0 sans livrable
 * validé, suspend la Face, gèle l'accès UGC + premium (via isUgcSuspended) sans
 * bloquer le login, rembourse l'escrow au Producteur (booking hybride seulement,
 * D-5.0.a/c), passe le shipment en `Suspended` et notifie les deux parties.
 *
 * Appelé en boucle depuis le cron ProcessUgcDeadlinesCommand : NO-THROW (un throw
 * planterait le tick et bloquerait les shipments suivants — cf. posture
 * UgcRefundService). Le mouvement d'argent + la ligne ugc_suspensions + le flip
 * du tunnel se font dans une SEULE transaction (atomicité) ; l'event est dispatché
 * APRÈS commit (un rollback tardif ne doit pas avoir notifié).
 */
class UgcSuspensionService
{
    public function __construct(
        private readonly EscrowService $escrow,
        private readonly WalletService $wallet,
    ) {}

    public function suspendForOverdueShipment(Shipment $shipment): void
    {
        try {
            $dispatch = null;

            DB::transaction(function () use ($shipment, &$dispatch): void {
                $dispatch = $this->suspendLocked($shipment);
            });

            // Après commit : un rollback tardif ne doit pas avoir déjà notifié.
            if ($dispatch !== null) {
                FaceUgcSuspended::dispatch(
                    $dispatch['shipment'],
                    $dispatch['reason'],
                    $dispatch['faceNewlySuspended'],
                );
            }
        } catch (\Throwable $e) {
            Log::critical('UGC suspension: échec — réconciliation requise', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Suspension SOUS LOCK, DANS la transaction de l'appelant. Retourne le payload
     * de dispatch (à émettre APRÈS commit) ou null sur garde déterministe (owner
     * inconnu, Face introuvable, shipment sorti des états actifs entre le SELECT du
     * cron et le lock). NE catch PAS : une erreur transitoire (crédit wallet) remonte
     * pour que la transaction de l'appelant rollback — le tick suivant réessaiera.
     *
     * @return array{shipment: Shipment, reason: UgcSuspensionReason, faceNewlySuspended: bool}|null
     */
    private function suspendLocked(Shipment $shipment): ?array
    {
        $owner = $shipment->owner;

        // 1. Résoudre faces.id selon l'owner (piège 2.4, calque NotifyFaceOnDeadlineApproaching).
        if ($owner instanceof Booking) {
            $faceId = (int) User::whereKey($owner->face_id)
                ->where('userable_type', Face::class)
                ->value('userable_id'); // booking.face_id = users.id → faces.id
        } elseif ($owner instanceof Candidature) {
            $faceId = (int) $owner->face_id; // déjà faces.id
        } else {
            return null;
        }

        if ($faceId <= 0) {
            return null;
        }

        // 2. Re-lock le shipment + re-check de l'état actif (sérialisation : suspendu /
        //    avancé entre le SELECT du cron et ce lock).
        /** @var Shipment $locked */
        $locked = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
        if (! in_array($locked->tunnel_status, [UgcTunnelStatus::Received, UgcTunnelStatus::AvisPending], true)) {
            return null;
        }

        // 3. Motif dérivé de l'état actif. Le re-check (étape 2) narrow le type à
        //    Received|AvisPending → match exhaustif SANS default (PHPStan le confirme :
        //    un default ici serait une branche morte).
        $reason = match ($locked->tunnel_status) {
            UgcTunnelStatus::Received => UgcSuspensionReason::UnboxingDeadlineMissed,
            UgcTunnelStatus::AvisPending => UgcSuspensionReason::AvisDeadlineMissed,
        };

        // 4. Idempotence Face-level : une seule suspension active par Face (lock + garde).
        $existing = UgcSuspension::query()
            ->where('face_id', $faceId)
            ->whereNull('reactivated_at')
            ->lockForUpdate()
            ->first();
        $faceNewlySuspended = $existing === null;

        if ($faceNewlySuspended) {
            UgcSuspension::create([
                'face_id' => $faceId,
                'shipment_id' => $locked->id,
                'reason' => $reason,
                'appeal_status' => UgcSuspensionAppealStatus::None,
                'suspended_at' => now(),
            ]);
        }

        // 5. Refund escrow booking-only (idempotent ; no-op produit-seul / candidature).
        if ($owner instanceof Booking) {
            $this->escrow->refundUgcSuspensionToProducer($owner, $this->wallet);
        }

        // 6. Sort le shipment du filtre cron [Received, AvisPending] → idempotence (AC8).
        $locked->update(['tunnel_status' => UgcTunnelStatus::Suspended]);

        return [
            'shipment' => $locked,
            'reason' => $reason,
            'faceNewlySuspended' => $faceNewlySuspended,
        ];
    }
}
