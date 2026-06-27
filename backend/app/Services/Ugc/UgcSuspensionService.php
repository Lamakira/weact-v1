<?php

declare(strict_types=1);

namespace App\Services\Ugc;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\MissionType;
use App\Enums\UgcSuspensionAppealStatus;
use App\Enums\UgcSuspensionReason;
use App\Enums\UgcTunnelStatus;
use App\Events\FaceUgcReactivated;
use App\Events\FaceUgcSuspended;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Shipment;
use App\Models\UgcSuspension;
use App\Models\User;
use App\Services\EscrowService;
use App\Services\MissionPaymentService;
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
        private readonly MissionPaymentService $missionPaymentService,
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

        // [5.3] Terminer en retard : si CE shipment EST le deal de la suspension active
        // (rouvert via resumeForLateCompletion), ne pas le re-suspendre — la deadline
        // dérivée est dans le passé (progress>=1.0) mais la Face régularise ; le dégel
        // viendra de la complétion (ReactivateFaceOnLateUgcCompletion), pas du cron.
        if ($existing !== null && $existing->shipment_id === $locked->id) {
            return null;
        }

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

        // 5. Refund escrow (idempotent). Booking → EscrowService ; Candidature hybride →
        //    mission_payment_candidatures (ugc-8-4, D-8.4.i ; couvre aussi la deadline ratée via
        //    le cron ProcessUgcDeadlines → suspendForOverdueShipment). No-op produit-seul (pas d'entry).
        if ($owner instanceof Booking) {
            $this->escrow->refundUgcSuspensionToProducer($owner, $this->wallet);
        } elseif ($owner instanceof Candidature) {
            $this->missionPaymentService->refundUgcCandidatureEscrow($owner, 'ugc_suspension');
        }

        // 6. Sort le shipment du filtre cron [Received, AvisPending] → idempotence (AC8).
        $locked->update(['tunnel_status' => UgcTunnelStatus::Suspended]);

        return [
            'shipment' => $locked,
            'reason' => $reason,
            'faceNewlySuspended' => $faceNewlySuspended,
        ];
    }

    /**
     * « Terminer en retard » (PO Q1) : rouvre le tunnel du deal suspendu
     * (Suspended → Received|AvisPending selon reason) pour ré-autoriser l'upload,
     * SANS dégeler (le dégel vient de la complétion — ReactivateFaceOnLateUgcCompletion).
     * Refuse si la fenêtre J+30 est dépassée (config source unique 5.2) ou si le deal
     * n'est plus en état Suspended / introuvable.
     *
     * @return 'resumed'|'window_closed'|'deal_unavailable'|'already_resumed'|'no_active_suspension'
     */
    public function resumeForLateCompletion(UgcSuspension $suspension): string
    {
        return DB::transaction(function () use ($suspension): string {
            /** @var UgcSuspension|null $locked */
            $locked = UgcSuspension::query()->lockForUpdate()->find($suspension->getKey());
            if ($locked === null || $locked->reactivated_at !== null) {
                return 'no_active_suspension';
            }

            $deadline = $locked->suspended_at
                ->copy()
                ->addDays((int) config('ugc.late_completion_days', 30));
            if (now()->greaterThan($deadline)) {
                return 'window_closed';
            }

            if ($locked->shipment_id === null) {
                return 'deal_unavailable';
            }

            /** @var Shipment|null $shipment */
            $shipment = Shipment::query()->lockForUpdate()->find($locked->shipment_id);
            if ($shipment === null) {
                return 'deal_unavailable';
            }
            if ($shipment->tunnel_status !== UgcTunnelStatus::Suspended) {
                return 'already_resumed';
            }

            // Deal sain (défensif : après suspension le booking reste Accepted UGC sans
            // flag refund — le refund suspension touche l'escrow, pas commission_refunded_at).
            $owner = $shipment->owner;
            if ($owner instanceof Booking) {
                if ($owner->type_contenu !== 'UGC' || $owner->status !== BookingStatus::Accepted
                    || $owner->commission_refund_requested_at !== null
                    || $owner->commission_refunded_at !== null) {
                    return 'deal_unavailable';
                }
            } elseif ($owner instanceof Candidature) {
                $mission = $owner->mission;
                if ($owner->status !== CandidatureStatus::Confirmed || $mission === null
                    || $mission->type_mission !== MissionType::Ugc
                    || $mission->commission_paid_at === null
                    || $mission->commission_refund_requested_at !== null
                    || $mission->commission_refunded_at !== null) {
                    return 'deal_unavailable';
                }
            } else {
                return 'deal_unavailable';
            }

            $shipment->update([
                'tunnel_status' => $locked->reason === UgcSuspensionReason::UnboxingDeadlineMissed
                    ? UgcTunnelStatus::Received
                    : UgcTunnelStatus::AvisPending,
            ]);

            return 'resumed';
        });
    }

    /**
     * Dégel (PO Q1/Q2) : lève la suspension (reactivated_at=now()) — source-agnostique
     * (complétion tardive / appel accepté / admin direct). Si un appel était Pending, le
     * marque Accepted. Idempotent (déjà réactivée → no-op, pas de 2ᵉ event). L'event
     * FaceUgcReactivated est dispatché APRÈS commit (calque suspendForOverdueShipment).
     */
    public function reactivate(UgcSuspension $suspension): void
    {
        /** @var UgcSuspension|null $reactivated */
        $reactivated = null;

        DB::transaction(function () use ($suspension, &$reactivated): void {
            /** @var UgcSuspension|null $locked */
            $locked = UgcSuspension::query()->lockForUpdate()->find($suspension->getKey());
            if ($locked === null || $locked->reactivated_at !== null) {
                return; // idempotent
            }

            $locked->update([
                'reactivated_at' => now(),
                'appeal_status' => $locked->appeal_status === UgcSuspensionAppealStatus::Pending
                    ? UgcSuspensionAppealStatus::Accepted
                    : $locked->appeal_status,
            ]);

            // [5.3 review] Dégel hors-complétion (admin direct / appel accepté) : si la Face
            // avait « repris » le deal (resumeForLateCompletion l'a rouvert en Received|AvisPending)
            // sans aller jusqu'à la complétion, le shipment est encore dans la fenêtre du cron
            // (deadline dérivée passée). Sans suspension active, la garde Option Z de suspendLocked
            // ne le protège plus → le prochain ugc:process-deadlines le re-suspendrait, annulant
            // cette réactivation. On clôture ce deal — financièrement mort (escrow déjà remboursé
            // à la suspension) — en le repassant Suspended. Le chemin complétion est intact : le
            // shipment y est déjà Completed (hors de cette condition).
            if ($locked->shipment_id !== null) {
                /** @var Shipment|null $shipment */
                $shipment = Shipment::query()->lockForUpdate()->find($locked->shipment_id);
                if ($shipment !== null
                    && in_array($shipment->tunnel_status, [UgcTunnelStatus::Received, UgcTunnelStatus::AvisPending], true)) {
                    $shipment->update(['tunnel_status' => UgcTunnelStatus::Suspended]);
                }
            }

            $reactivated = $locked;
        });

        // Après commit : un rollback tardif ne doit pas avoir notifié (calque suspendForOverdueShipment).
        if ($reactivated !== null) {
            FaceUgcReactivated::dispatch($reactivated);
        }
    }

    /** Cycle d'appel — la Face ouvre un appel : None → Pending (revue admin). */
    public function openAppeal(UgcSuspension $suspension): string
    {
        return DB::transaction(function () use ($suspension): string {
            /** @var UgcSuspension|null $locked */
            $locked = UgcSuspension::query()->lockForUpdate()->find($suspension->getKey());
            if ($locked === null || $locked->reactivated_at !== null) {
                return 'no_active_suspension';
            }
            if ($locked->appeal_status !== UgcSuspensionAppealStatus::None) {
                return 'appeal_exists';
            }
            $locked->update(['appeal_status' => UgcSuspensionAppealStatus::Pending]);

            return 'opened';
        });
    }

    /** Admin — rejette un appel Pending : reste suspendu (reactivated_at inchangé). */
    public function rejectAppeal(UgcSuspension $suspension): string
    {
        return DB::transaction(function () use ($suspension): string {
            /** @var UgcSuspension|null $locked */
            $locked = UgcSuspension::query()->lockForUpdate()->find($suspension->getKey());
            if ($locked === null || $locked->reactivated_at !== null) {
                return 'no_active_suspension';
            }
            if ($locked->appeal_status !== UgcSuspensionAppealStatus::Pending) {
                return 'no_pending_appeal';
            }
            $locked->update(['appeal_status' => UgcSuspensionAppealStatus::Rejected]);

            return 'rejected';
        });
    }
}
