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
use App\Models\Producer;
use App\Models\User;
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
    ) {}

    public function settleRefundForBooking(Booking $booking, UgcRefundReason $reason): void
    {
        try {
            $settled = null;

            DB::transaction(function () use ($booking, $reason, &$settled): void {
                /** @var Booking $locked */
                $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

                // Idempotence sur le settlement (D-2.6.b) : hors-périmètre ou déjà
                // réglé → no-op (jamais de second crédit wallet).
                if ($locked->type_contenu !== 'UGC' || $locked->commission_refunded_at !== null) {
                    return;
                }

                if ($locked->commission_paid_at === null) {
                    // Commission jamais encaissée : rien à recréditer.
                    Log::critical('UGC refund: demande sans encaissement — rien à créditer', [
                        'booking_id' => $locked->id,
                        'reason' => $reason->value,
                    ]);

                    return;
                }

                if ((int) $locked->commission_ugc <= 0) {
                    // Montant de commission absent/invalide (anomalie de données) :
                    // ne pas marquer remboursé ni créditer 0 — réconciliation requise.
                    Log::critical('UGC refund: commission_ugc absente/invalide — rien à créditer', [
                        'booking_id' => $locked->id,
                        'reason' => $reason->value,
                    ]);

                    return;
                }

                $locked->update([
                    'commission_refund_requested_at' => $locked->commission_refund_requested_at ?? now(),
                    'commission_refund_reason' => $reason,
                    'commission_refunded_at' => now(),
                ]);

                // Crédit wallet Producteur (producer_id = users.id pour un booking).
                $this->wallet->credit(
                    (int) $locked->producer_id,
                    (int) $locked->commission_ugc,
                    $locked,
                    WalletCreditMotif::UgcCommissionRefund->label(),
                );

                // Audit booking-scopé (D-2.6.g) ; ref = transaction d'origine (stable, 1/owner).
                $ref = (string) $locked->fedapay_transaction_id;
                if (! $this->hasExistingFinancialEvent($locked->id, FinancialEventType::Refund, $ref)) {
                    $this->recordFinancialEvent(
                        FinancialEventType::Refund,
                        $locked,
                        (int) $locked->commission_ugc,
                        ['fedapay_ref' => $ref, 'status' => 'refunded',
                            'metadata' => ['kind' => 'ugc_commission', 'settlement' => 'wallet']],
                    );
                }

                $settled = $locked->fresh();
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

                // Idempotence sur le settlement (D-2.6.b) : hors-périmètre ou déjà
                // réglé → no-op (jamais de second crédit wallet).
                if ($locked->type_mission !== MissionType::Ugc || $locked->commission_refunded_at !== null) {
                    return;
                }

                if ($locked->commission_paid_at === null) {
                    Log::critical('UGC refund: demande mission sans encaissement — rien à créditer', [
                        'mission_id' => $locked->id,
                        'reason' => $reason->value,
                    ]);

                    return;
                }

                if ((int) $locked->commission_ugc <= 0) {
                    // Montant de commission absent/invalide (anomalie de données) :
                    // ne pas marquer remboursé ni créditer 0 — réconciliation requise.
                    Log::critical('UGC refund: commission_ugc mission absente/invalide — rien à créditer', [
                        'mission_id' => $locked->id,
                        'reason' => $reason->value,
                    ]);

                    return;
                }

                // missions.producer_id = producers.id → résoudre le users.id du
                // Producteur (calque exact NotifyProducerOnUgcRefunded).
                $producerUserId = User::where('userable_type', Producer::class)
                    ->where('userable_id', $locked->producer_id)
                    ->value('id');

                if ($producerUserId === null) {
                    // Producteur orphelin : ne PAS poser commission_refunded_at
                    // (laisser en attente pour réconciliation), jamais de throw (AC2).
                    Log::critical('UGC refund: producteur introuvable pour la mission — settlement différé', [
                        'mission_id' => $locked->id,
                        'producer_id' => $locked->producer_id,
                        'reason' => $reason->value,
                    ]);

                    return;
                }

                $update = [
                    'commission_refund_requested_at' => $locked->commission_refund_requested_at ?? now(),
                    'commission_refund_reason' => $reason,
                    'commission_refunded_at' => now(),
                ];

                // Défense-en-profondeur (restaure le force-close 2.5 supprimé) : une
                // mission remboursée ne doit jamais rester découvrable/acceptable. En
                // nominal elle est déjà Closed (closeMissionPastDeadlineWithoutEngagement) ;
                // ce filet couvre une réconciliation ops directe (runbook §4/§6) sur une
                // mission encore Published — aucune policy mission ne garde commission_refunded_at.
                if ($locked->status === MissionStatus::Published) {
                    $update['status'] = MissionStatus::Closed;
                }

                $locked->update($update);

                // Crédit wallet direct (pas de Booking pour une mission). Pas de
                // FinancialEvent : audit mission par colonnes (D-2.6.g / D-1.5.g).
                $this->wallet->creditDirect(
                    (int) $producerUserId,
                    (int) $locked->commission_ugc,
                    WalletCreditMotif::UgcCommissionRefund->label(),
                );

                $settled = $locked->fresh();
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

    public function expireBookingPastAcceptanceWindow(Booking $booking): bool
    {
        try {
            $expired = DB::transaction(function () use ($booking): bool {
                /** @var Booking $locked */
                $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

                if ($locked->type_contenu !== 'UGC'
                    || $locked->status !== BookingStatus::CommissionPaid
                    || $locked->commission_refunded_at !== null) {
                    // re-check sous lock : accepté/refusé entre-temps, ou déjà remboursé
                    // (le deal est mort — rien à expirer ni à demander).
                    return false;
                }

                // PAS de dispatch BookingExpired (copy cash inadaptée — D-2.5.g) ;
                // le Producteur est notifié par le crédit wallet.
                $locked->update(['status' => BookingStatus::Expired]);

                return true;
            });

            if ($expired) {
                $this->settleRefundForBooking($booking->fresh(), UgcRefundReason::AcceptanceWindowExpired);
            }

            return $expired;
        } catch (\Throwable $e) {
            Log::critical('UGC refund: échec expiration booking', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function closeMissionPastDeadlineWithoutEngagement(Mission $mission): bool
    {
        try {
            $closed = DB::transaction(function () use ($mission): bool {
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

                return true;
            });

            if ($closed) {
                $this->settleRefundForMission($mission->fresh(), UgcRefundReason::MissionDeadlineExpired);
            }

            return $closed;
        } catch (\Throwable $e) {
            Log::critical('UGC refund: échec clôture mission', [
                'mission_id' => $mission->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
