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
use App\Events\UgcCommissionRefunded;
use App\Events\UgcCommissionRefundRequested;
use App\Mail\UgcRefundRequestedMail;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Mission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Cycle de vie du remboursement de la commission UGC (D4 / NFR1 : « remboursée
 * si refus ») — flux OPS, pas de refund SDK (spike OI-2) :
 * demande (refus Face / fenêtre expirée / mission sans participant) → trace +
 * mail ops + notification Producteur → remboursement manuel dashboard FedaPay →
 * webhook transaction.refunded → settlement (commission_refunded_at).
 *
 * TOUTES les méthodes sont no-throw sur les états métier inattendus
 * (Log::critical + no-op) : appelées depuis un post-commit d'API, un cron et
 * le webhook, aucune ne doit propager (project_fedapay_webhook_no_throw).
 * FinancialEvent Refund : booking uniquement (trait booking-scopé, D-1.5.g).
 */
class UgcRefundService
{
    use RecordsFinancialEvent;

    public function requestRefundForBooking(Booking $booking, UgcRefundReason $reason): void
    {
        try {
            $updated = DB::transaction(function () use ($booking, $reason): ?Booking {
                /** @var Booking $locked */
                $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

                if ($locked->type_contenu !== 'UGC'
                    || $locked->commission_refund_requested_at !== null
                    || $locked->commission_refunded_at !== null) {
                    // hors-périmètre, demande déjà posée (idempotent), ou déjà remboursé
                    // hors-procédure (D-2.5.h) — ne jamais redemander un refund réglé.
                    return null;
                }

                if ($locked->fedapay_transaction_id === null) {
                    Log::critical('UGC refund: demande sans transaction FedaPay — rien à rembourser', [
                        'booking_id' => $locked->id,
                        'reason' => $reason->value,
                    ]);

                    return null;
                }

                $locked->update([
                    'commission_refund_requested_at' => now(),
                    'commission_refund_reason' => $reason,
                ]);

                return $locked->fresh();
            });

            if ($updated) {
                $this->notifyOps($updated, $reason);
                UgcCommissionRefundRequested::dispatch($updated);
            }
        } catch (\Throwable $e) {
            Log::critical('UGC refund: échec de la demande booking — réconciliation runbook requise', [
                'booking_id' => $booking->id,
                'reason' => $reason->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function requestRefundForMission(Mission $mission, UgcRefundReason $reason): void
    {
        try {
            $updated = DB::transaction(function () use ($mission, $reason): ?Mission {
                /** @var Mission $locked */
                $locked = Mission::query()->lockForUpdate()->findOrFail($mission->id);

                if ($locked->type_mission !== MissionType::Ugc
                    || $locked->commission_refund_requested_at !== null
                    || $locked->commission_refunded_at !== null) {
                    // hors-périmètre, demande déjà posée (idempotent), ou déjà remboursé
                    // hors-procédure (D-2.5.h) — ne jamais redemander un refund réglé.
                    return null;
                }

                if ($locked->fedapay_transaction_id === null || $locked->commission_paid_at === null) {
                    Log::critical('UGC refund: demande mission sans encaissement — rien à rembourser', [
                        'mission_id' => $locked->id,
                        'reason' => $reason->value,
                    ]);

                    return null;
                }

                $locked->update([
                    'commission_refund_requested_at' => now(),
                    'commission_refund_reason' => $reason,
                ]);

                return $locked->fresh();
            });

            if ($updated) {
                $this->notifyOps($updated, $reason);
                UgcCommissionRefundRequested::dispatch($updated);
            }
        } catch (\Throwable $e) {
            Log::critical('UGC refund: échec de la demande mission — réconciliation runbook requise', [
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
                    // hors-procédure (le deal est mort — rien à expirer ni à demander).
                    return false;
                }

                // PAS de dispatch BookingExpired (copy cash inadaptée — D-2.5.g) ;
                // le Producteur est notifié par la demande de remboursement.
                $locked->update(['status' => BookingStatus::Expired]);

                return true;
            });

            if ($expired) {
                $this->requestRefundForBooking($booking->fresh(), UgcRefundReason::AcceptanceWindowExpired);
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
                    || $locked->isAcceptingCandidatures()) {
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
                $this->requestRefundForMission($mission->fresh(), UgcRefundReason::MissionDeadlineExpired);
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

    public function markBookingCommissionRefunded(Booking $booking, string $fedapayRef): Booking
    {
        $settled = null;

        $result = DB::transaction(function () use ($booking, $fedapayRef, &$settled) {
            /** @var Booking $booking */
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($booking->type_contenu !== 'UGC') {
                // Hors-périmètre (appel tinker runbook §4 sur un mauvais id) : un
                // booking cash ne porte pas de commission UGC à régler.
                Log::critical('UGC refund: settlement demandé sur un booking non-UGC — ignoré', [
                    'booking_id' => $booking->id,
                    'fedapay_ref' => $fedapayRef,
                ]);

                return $booking;
            }

            if ($booking->commission_refunded_at !== null) {
                return $booking; // idempotent
            }

            if ($this->hasExistingFinancialEvent($booking->id, FinancialEventType::Refund, $fedapayRef)) {
                return $booking; // garde double event
            }

            if ($booking->commission_refund_requested_at === null) {
                // Refund ops hors-procédure : on enregistre quand même (l'argent a bougé
                // chez FedaPay), statut métier inchangé (D-2.5.h). Jamais de throw.
                Log::critical('UGC refund: remboursement FedaPay reçu SANS demande locale', [
                    'booking_id' => $booking->id,
                    'status' => $booking->status->value,
                    'fedapay_ref' => $fedapayRef,
                ]);
            }

            $booking->update(['commission_refunded_at' => now()]);
            $fresh = $booking->fresh();

            $this->recordFinancialEvent(
                FinancialEventType::Refund,
                $fresh,
                (int) $fresh->commission_ugc,
                ['fedapay_ref' => $fedapayRef, 'status' => 'refunded', 'metadata' => ['kind' => 'ugc_commission']],
            );

            $settled = $fresh;

            return $fresh;
        });

        // Après commit (AC6) : un rollback tardif ne doit pas avoir déjà notifié.
        if ($settled) {
            UgcCommissionRefunded::dispatch($settled);
        }

        return $result;
    }

    public function markMissionCommissionRefunded(Mission $mission, string $fedapayRef): Mission
    {
        $settled = null;

        $result = DB::transaction(function () use ($mission, $fedapayRef, &$settled) {
            /** @var Mission $mission */
            $mission = Mission::query()->lockForUpdate()->findOrFail($mission->id);

            if ($mission->type_mission !== MissionType::Ugc) {
                // Hors-périmètre (appel tinker runbook §4 sur un mauvais id) : une
                // mission standard ne porte pas de commission de publication UGC.
                Log::critical('UGC refund: settlement demandé sur une mission non-UGC — ignoré', [
                    'mission_id' => $mission->id,
                    'fedapay_ref' => $fedapayRef,
                ]);

                return $mission;
            }

            if ($mission->commission_refunded_at !== null) {
                return $mission; // idempotent (pas de FinancialEvent mission — D-1.5.g)
            }

            if ($mission->status === MissionStatus::Published) {
                // Refund ops sur une mission encore en vitrine : un paywall ne doit
                // pas vendre une mission remboursée (AC8c) — on la ferme avec le
                // settlement, dans la même transaction.
                Log::critical('UGC refund: remboursement FedaPay reçu sur une mission encore publiée — clôture forcée', [
                    'mission_id' => $mission->id,
                    'fedapay_ref' => $fedapayRef,
                ]);

                $mission->update([
                    'status' => MissionStatus::Closed,
                    'commission_refunded_at' => now(),
                ]);
            } else {
                $mission->update(['commission_refunded_at' => now()]);
            }

            $fresh = $mission->fresh();
            $settled = $fresh;

            return $fresh;
        });

        // Après commit (AC6) : un rollback tardif ne doit pas avoir déjà notifié.
        if ($settled) {
            UgcCommissionRefunded::dispatch($settled);
        }

        return $result;
    }

    private function notifyOps(Booking|Mission $owner, UgcRefundReason $reason): void
    {
        $adminEmail = (string) config('app.admin_email', '');

        if ($adminEmail === '') {
            Log::warning('UGC refund: demande créée mais admin_email non configuré — aucun mail ops envoyé', [
                'owner_type' => $owner::class,
                'owner_id' => $owner->id,
            ]);

            return;
        }

        try {
            Mail::to($adminEmail)->send(new UgcRefundRequestedMail($owner, $reason));
        } catch (\Throwable $e) {
            Log::error('UGC refund: échec envoi mail ops', [
                'owner_type' => $owner::class,
                'owner_id' => $owner->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
