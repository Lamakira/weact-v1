<?php

declare(strict_types=1);

namespace App\Services\Ugc;

use App\Concerns\RecordsFinancialEvent;
use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\FinancialEventType;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Enums\UgcRefundReason;
use App\Enums\WalletCreditMotif;
use App\Events\UgcCommissionRefunded;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Mission;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cycle de vie du remboursement de la commission UGC (D4 / NFR1 : « remboursée
 * si refus ») — settlement = CRÉDIT WALLET interne (story 2.6, SUPERSEDE le
 * refund FedaPay manuel de la 2.5) : détection (refus Face / fenêtre expirée /
 * mission sans participant) ET règlement dans la MÊME transaction
 * (commission_refund_requested_at + commission_refunded_at posés ensemble,
 * D-2.6.b). Le money-out réel se fait au retrait (flux WithdrawalService
 * existant) ; le crédit wallet crée la dette envers le Producteur.
 *
 * TOUTES les méthodes sont no-throw sur les états métier inattendus
 * (Log::critical + no-op) : appelées depuis un post-commit d'API et un cron,
 * aucune ne doit propager (project_fedapay_webhook_no_throw).
 * FinancialEvent Refund : booking uniquement (trait booking-scopé, D-2.6.g) ;
 * la mission est auditée par ses colonnes commission_refund*.
 */
class UgcRefundService
{
    use RecordsFinancialEvent;

    public function __construct(
        private readonly WalletService $wallet,
        private readonly \App\Services\EscrowService $escrow,
    ) {}

    public function settleRefundForBooking(Booking $booking, UgcRefundReason $reason): void
    {
        try {
            $settled = null;

            DB::transaction(function () use ($booking, $reason, &$settled): void {
                /** @var Booking $locked */
                $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);
                $settled = $this->settleLockedBooking($locked, $reason);
            });

            // Après commit (AC1) : un rollback tardif ne doit pas avoir déjà notifié.
            if ($settled) {
                UgcCommissionRefunded::dispatch($settled);
            }
        } catch (\Throwable $e) {
            Log::critical('UGC refund: échec settlement wallet booking — réconciliation requise', [
                'booking_id' => $booking->id,
                'reason' => $reason->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function settleRefundForMission(Mission $mission, UgcRefundReason $reason): void
    {
        try {
            $settled = null;

            DB::transaction(function () use ($mission, $reason, &$settled): void {
                /** @var Mission $locked */
                $locked = Mission::query()->lockForUpdate()->findOrFail($mission->id);
                $settled = $this->settleLockedMission($locked, $reason);
            });

            // Après commit (AC2) : un rollback tardif ne doit pas avoir déjà notifié.
            if ($settled) {
                UgcCommissionRefunded::dispatch($settled);
            }
        } catch (\Throwable $e) {
            Log::critical('UGC refund: échec settlement wallet mission — réconciliation requise', [
                'mission_id' => $mission->id,
                'reason' => $reason->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Règlement booking SOUS LOCK, DANS la transaction de l'appelant. Retourne le
     * booking réglé (à dispatcher APRÈS le commit) ou null sur garde déterministe
     * (hors-périmètre, déjà réglé, sans encaissement, commission invalide).
     *
     * NE catch PAS volontairement : une erreur transitoire (crédit wallet) remonte
     * pour que la transaction de l'appelant rollback — le chemin cron réessaiera alors
     * au tick suivant (settlement atomique avec le changement de statut, revue 2.6 #1).
     * Les points d'entrée publics (refuse, tinker) l'enrobent d'un try/no-throw.
     */
    private function settleLockedBooking(Booking $locked, UgcRefundReason $reason): ?Booking
    {
        // Type-guard owner-spécifique + gardes déterministes partagées (D-2.6.b).
        if ($locked->type_contenu !== 'UGC'
            || ! $this->guardSharedRefundPreconditions($locked, 'booking_id', $reason)) {
            return null;
        }

        $locked->update($this->refundStampColumns($locked, $reason));

        // RH.2 : dénoue l'escrow du net Face de l'hybride (no-op produit-seul : aucun escrow).
        // Ne déplace pas d'argent ni n'enregistre de FinancialEvent (le crédit complet + l'audit
        // Refund ci-dessous sont l'unique mouvement) — D-RH2.g.
        $this->escrow->markRefundedForUgc($locked);

        // RH.2 : crédit wallet Producteur du règlement COMPLET (cash + frais service en hybride ;
        // commission seule en produit-seul = montant identique à avant RH.2). producer_id = users.id.
        $this->wallet->credit(
            (int) $locked->producer_id,
            (int) $locked->montant_total_producteur,
            $locked,
            WalletCreditMotif::UgcSettlementRefund->label(),
        );

        // Audit booking-scopé (D-2.6.g) ; ref = transaction d'origine (stable, 1/owner).
        // L'idempotence est garantie par la garde commission_refunded_at sous
        // lockForUpdate (ce bloc ne s'exécute qu'une fois par booking) → pas de
        // re-check hasExistingFinancialEvent (revue 2.6 #10 : SELECT redondant).
        $ref = (string) $locked->fedapay_transaction_id;
        $this->recordFinancialEvent(
            FinancialEventType::Refund,
            $locked,
            (int) $locked->montant_total_producteur,
            ['fedapay_ref' => $ref, 'status' => 'refunded',
                'metadata' => ['kind' => 'ugc_settlement', 'settlement' => 'wallet']],
        );

        return $locked->fresh();
    }

    /**
     * Règlement mission SOUS LOCK, DANS la transaction de l'appelant. Retourne la
     * mission réglée (à dispatcher APRÈS le commit) ou null sur garde déterministe
     * (hors-périmètre, déjà réglé, sans encaissement, commission invalide, producteur
     * orphelin — AC2). NE catch PAS (cf. settleLockedBooking).
     */
    private function settleLockedMission(Mission $locked, UgcRefundReason $reason): ?Mission
    {
        // Type-guard owner-spécifique + gardes déterministes partagées (D-2.6.b).
        if ($locked->type_mission !== MissionType::Ugc
            || ! $this->guardSharedRefundPreconditions($locked, 'mission_id', $reason)) {
            return null;
        }

        // missions.producer_id = producers.id → User via la relation Producer::user()
        // (morphOne userable). Producteur orphelin → settlement différé (AC2).
        $producerUser = $locked->producer?->user;

        if ($producerUser === null) {
            // Ne PAS poser commission_refunded_at (laisser en attente pour
            // réconciliation), jamais de throw (AC2).
            Log::critical('UGC refund: producteur introuvable pour la mission — settlement différé', [
                'mission_id' => $locked->id,
                'producer_id' => $locked->producer_id,
                'reason' => $reason->value,
            ]);

            return null;
        }

        $update = $this->refundStampColumns($locked, $reason);

        // Défense-en-profondeur (restaure le force-close 2.5 supprimé) : une mission
        // remboursée ne doit jamais rester découvrable/acceptable. En nominal elle est
        // déjà Closed (closeMissionPastDeadlineWithoutEngagement) ; ce filet couvre une
        // réconciliation ops directe (runbook §4/§6) sur une mission encore Published —
        // aucune policy mission ne garde commission_refunded_at.
        if ($locked->status === MissionStatus::Published) {
            $update['status'] = MissionStatus::Closed;
        }

        $locked->update($update);

        // Crédit wallet direct (pas de Booking pour une mission). Pas de
        // FinancialEvent : audit mission par colonnes (D-2.6.g / D-1.5.g).
        $this->wallet->creditDirect(
            (int) $producerUser->getKey(),
            (int) $locked->commission_ugc,
            WalletCreditMotif::UgcCommissionRefund->label(),
        );

        return $locked->fresh();
    }

    /**
     * Gardes déterministes partagées booking/mission (après le type-guard de
     * l'appelant) : déjà réglé (idempotence D-2.6.b, no-op silencieux), sans
     * encaissement, ou commission absente/invalide (anomalie loggée). Le owner est
     * distingué dans le contexte du log par $ownerKey ('booking_id' | 'mission_id').
     */
    private function guardSharedRefundPreconditions(Booking|Mission $locked, string $ownerKey, UgcRefundReason $reason): bool
    {
        if ($locked->commission_refunded_at !== null) {
            return false;
        }

        if ($locked->commission_paid_at === null) {
            Log::critical('UGC refund: demande sans encaissement — rien à créditer', [
                $ownerKey => $locked->id,
                'reason' => $reason->value,
            ]);

            return false;
        }

        if ((int) $locked->commission_ugc <= 0) {
            // Montant absent/invalide (anomalie) : ne pas marquer remboursé ni créditer 0.
            Log::critical('UGC refund: commission_ugc absente/invalide — rien à créditer', [
                $ownerKey => $locked->id,
                'reason' => $reason->value,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Les 3 colonnes refund posées ensemble (D-2.6.b), identiques booking/mission.
     * Le `?? now()` préserve l'horodatage d'une éventuelle demande legacy 2.5.
     *
     * @return array<string, mixed>
     */
    private function refundStampColumns(Booking|Mission $locked, UgcRefundReason $reason): array
    {
        return [
            'commission_refund_requested_at' => $locked->commission_refund_requested_at ?? now(),
            'commission_refund_reason' => $reason,
            'commission_refunded_at' => now(),
        ];
    }

    public function expireBookingPastAcceptanceWindow(Booking $booking): bool
    {
        try {
            $settled = null;

            $expired = DB::transaction(function () use ($booking, &$settled): bool {
                /** @var Booking $locked */
                $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

                if ($locked->type_contenu !== 'UGC'
                    || $locked->status !== BookingStatus::CommissionPaid
                    || $locked->commission_refunded_at !== null) {
                    // re-check sous lock : accepté/refusé entre-temps, ou déjà remboursé
                    // (le deal est mort — rien à expirer ni à régler).
                    return false;
                }

                // PAS de dispatch BookingExpired (copy cash inadaptée — D-2.5.g) ;
                // le Producteur est notifié par le crédit wallet.
                $locked->update(['status' => BookingStatus::Expired]);

                // Settlement ATOMIQUE dans la MÊME transaction (revue 2.6 #1) : si le
                // crédit wallet jette, l'expiration est annulée (rollback) → le cron
                // réessaiera au tick suivant (le booking reste commission_paid). Une garde
                // déterministe (commission invalide) renvoie null → on expire sans créditer.
                $settled = $this->settleLockedBooking($locked, UgcRefundReason::AcceptanceWindowExpired);

                return true;
            });

            if ($settled) {
                UgcCommissionRefunded::dispatch($settled);
            }

            return $expired;
        } catch (\Throwable $e) {
            Log::critical('UGC refund: échec expiration/settlement booking — réconciliation requise', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function closeMissionPastDeadlineWithoutEngagement(Mission $mission): bool
    {
        try {
            $settled = null;

            $closed = DB::transaction(function () use ($mission, &$settled): bool {
                /** @var Mission $locked */
                $locked = Mission::query()->lockForUpdate()->findOrFail($mission->id);

                if ($locked->type_mission !== MissionType::Ugc
                    || $locked->status !== MissionStatus::Published
                    || $locked->commission_paid_at === null
                    || $locked->commission_refunded_at !== null
                    || $locked->isAcceptingCandidatures()) {
                    // déjà réglée hors-cron → ne pas re-clôturer ni re-créditer (symétrie booking)
                    return false;
                }

                // Mêmes états engagés que la capacité 2.4 ; re-compté sous lock mission
                // (sérialisé avec UgcEngagementController::accept qui locke aussi).
                $engaged = Candidature::where('mission_id', $locked->id)
                    ->whereIn('status', [
                        CandidatureStatus::Confirmed->value,
                        CandidatureStatus::InProgress->value,
                        CandidatureStatus::Completed->value,
                    ])
                    ->count();

                if ($engaged > 0) {
                    return false; // engagement partiel : commission consommée (D-2.5.d)
                }

                $locked->update(['status' => MissionStatus::Closed]);

                // Settlement ATOMIQUE dans la MÊME transaction (revue 2.6 #1) : si le
                // crédit wallet jette, la clôture est annulée (rollback) → le cron
                // réessaiera au tick suivant (la mission reste published). Producteur
                // orphelin → null (clôture conservée, refund différé à la réconciliation, AC2).
                $settled = $this->settleLockedMission($locked, UgcRefundReason::MissionDeadlineExpired);

                return true;
            });

            if ($settled) {
                UgcCommissionRefunded::dispatch($settled);
            }

            return $closed;
        } catch (\Throwable $e) {
            Log::critical('UGC refund: échec clôture/settlement mission — réconciliation requise', [
                'mission_id' => $mission->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
