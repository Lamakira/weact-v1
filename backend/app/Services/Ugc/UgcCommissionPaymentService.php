<?php

declare(strict_types=1);

namespace App\Services\Ugc;

use App\Concerns\RecordsFinancialEvent;
use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\FinancialEventType;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Events\BookingCommissionPaid;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPaymentCandidature;
use App\Services\FaceEntitlementService;
use App\Services\FedapayService;
use App\ValueObjects\BookingPricing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Dedicated settlement service for UGC commission payments (D-1.5.b ; RH.2).
 *
 * RH.2 : the producer settlement is `montant_total_producteur` (hybrid booking = cash
 * + service fee ; product-only booking = the product commission). The Face's net
 * (`montant_face_recoit`) is escrowed at settlement for the hybrid booking only; the
 * product-only path and the mission path have NO escrow (nothing to sequester —
 * the product is off-platform; the mission has no per-engagement escrow).
 *  - a paid UGC booking transitions pending → BookingStatus::CommissionPaid;
 *  - a paid UGC mission transitions pending_payment → MissionStatus::Published.
 *
 * Booking settlements record FinancialEvent audit rows (via the booking-scoped
 * RecordsFinancialEvent trait). Mission settlements DO NOT (D-1.5.g): the mission
 * audit is `commission_paid_at` + `fedapay_transaction_id`, idempotent on
 * `status === Published`.
 */
class UgcCommissionPaymentService
{
    use RecordsFinancialEvent;

    /**
     * FedaPay statuses that mean an in-flight transaction can no longer be reused
     * (the producer must get a brand-new checkout).
     *
     * @var list<string>
     */
    private const TERMINAL_FAILED_STATUSES = ['declined', 'canceled', 'refunded'];

    /**
     * Statut de paiement de commission dérivé du dernier checkAndProcess* (ugc-3-5 Item 3, exposition lecture).
     * 'paid' | 'pending' | 'failed'. Stateful par requête : un seul checkAndProcess* par requête de polling.
     *
     * ⚠️ NE PAS enregistrer ce service en singleton (`$this->app->singleton(...)`). Cet état est
     * volontairement par-instance/par-requête : le contrôleur appelle checkAndProcess* PUIS lit
     * lastCommissionPaymentStatus() sur la même instance. Un binding singleton ferait fuiter la
     * valeur 'failed' d'une requête à l'autre. Aujourd'hui résolu frais par requête (binding par défaut).
     */
    private string $lastCommissionPaymentStatus = 'pending';

    public function __construct(
        private readonly FedapayService $fedapayService,
        private readonly \App\Services\EscrowService $escrowService,
        private readonly FaceEntitlementService $faceEntitlementService,
    ) {}

    // -------------------------------------------------------------------------
    // Booking commission (pending → commission_paid)
    // -------------------------------------------------------------------------

    /**
     * Initiate a FedaPay checkout for a UGC booking commission.
     * Idempotent: a second call while a transaction is in flight regenerates the
     * checkout URL of the existing transaction instead of creating a new one.
     *
     * @return array{booking: Booking, checkout_url: string}
     *
     * @throws ValidationException
     */
    public function initiateForBooking(Booking $booking): array
    {
        $lock = Cache::lock("ugc_commission_booking_{$booking->id}", 30);

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'status' => ['Un paiement est deja en cours pour cette commission.'],
            ]);
        }

        try {
            /** @var Booking $booking */
            $booking = Booking::query()->findOrFail($booking->id);

            $this->assertBookingPayable($booking);

            if ($booking->fedapay_transaction_id !== null) {
                $existing = $this->fedapayService->retrieveTransaction((int) $booking->fedapay_transaction_id);

                if (! in_array($existing->status, self::TERMINAL_FAILED_STATUSES, true)) {
                    $regenerated = $this->fedapayService->regenerateTokenFromTransaction($existing);

                    return [
                        'booking' => $booking->fresh(),
                        'checkout_url' => $regenerated['checkout_url'],
                    ];
                }

                // Terminal failure — clear so a fresh checkout can be created.
                $booking->update(['fedapay_transaction_id' => null, 'payment_initiation_key' => null]);
                $booking = $booking->fresh();
            }

            $idempotencyKey = $booking->payment_initiation_key ?: Str::uuid()->toString();
            $booking->update(['payment_initiation_key' => $idempotencyKey]);

            $result = $this->fedapayService->initiatePaymentForUgcBooking($booking->fresh(), $idempotencyKey);

            return DB::transaction(function () use ($booking, $result, $idempotencyKey): array {
                /** @var Booking $locked */
                $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

                if ($locked->fedapay_transaction_id !== null) {
                    return ['booking' => $locked->fresh(), 'checkout_url' => $result['checkout_url']];
                }

                $locked->update([
                    'fedapay_transaction_id' => $result['fedapay_transaction_id'],
                    'payment_initiation_key' => null,
                ]);

                $this->recordFinancialEvent(
                    FinancialEventType::PaymentInitiated,
                    $locked->fresh(),
                    (int) $locked->montant_total_producteur, // RH.2 : règlement complet (cash + frais service ; produit-seul = commission)
                    ['idempotency_key' => $idempotencyKey, 'metadata' => ['kind' => 'ugc_commission']],
                );

                return ['booking' => $locked->fresh(), 'checkout_url' => $result['checkout_url']];
            });
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Settle an approved UGC booking: pending → commission_paid.
     * RH.2 : records the full settlement (`montant_total_producteur`) and escrows the
     * Face's net (`montant_face_recoit`) for the hybrid booking (product-only = 0 → no
     * escrow). Idempotent. A payment landing on a non-pending (terminal) booking is
     * logged for ops and no-oped — never thrown — so the webhook job is never poisoned
     * into a retry storm.
     */
    public function markBookingCommissionPaid(Booking $booking, string $fedapayRef): Booking
    {
        return DB::transaction(function () use ($booking, $fedapayRef) {
            /** @var Booking $booking */
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($booking->status === BookingStatus::CommissionPaid) {
                return $booking; // idempotent
            }

            if ($booking->status !== BookingStatus::Pending) {
                // Statut terminal (annulé/expiré/refusé) mais un paiement est tout de
                // même arrivé. NE PAS throw : l'exception s'échapperait de
                // HandleFedapayWebhook::handle() avant markProcessed() et le job
                // (ShouldQueue) partirait en retry storm vers failed_jobs. On
                // journalise pour traitement ops — le remboursement réel = story 2.5.
                Log::critical('UGC: paiement reçu sur un booking non-payable — remboursement ops requis', [
                    'booking_id' => $booking->id,
                    'status' => $booking->status->value,
                    'fedapay_ref' => $fedapayRef,
                ]);

                return $booking;
            }

            if ($this->hasExistingFinancialEvent($booking->id, FinancialEventType::PaymentConfirmed)) {
                return $booking; // guard double event
            }

            $booking->update(['status' => BookingStatus::CommissionPaid, 'commission_paid_at' => now()]);
            $fresh = $booking->fresh();

            $this->recordFinancialEvent(
                FinancialEventType::PaymentConfirmed,
                $fresh,
                (int) $fresh->montant_total_producteur, // RH.2 : règlement complet encaissé (calque markAsPaid cash)
                ['fedapay_ref' => $fedapayRef, 'status' => 'confirmed', 'metadata' => ['kind' => 'ugc_commission']],
            );

            // RH.2 : séquestre le net Face de l'hybride (montant_face_recoit > 0). Produit-seul = 0 → pas d'escrow.
            if ((int) $fresh->montant_face_recoit > 0) {
                $this->escrowService->lock($fresh);
            }

            BookingCommissionPaid::dispatch($fresh);

            return $fresh;
        });
    }

    /**
     * Record a failed/cancelled UGC booking commission attempt.
     * Status is unchanged (stays pending) so the producer can retry. Idempotent
     * per (booking, PaymentFailed, fedapayRef).
     */
    public function markBookingCommissionFailed(Booking $booking, string $fedapayRef, string $reason): void
    {
        DB::transaction(function () use ($booking, $fedapayRef, $reason): void {
            /** @var Booking $booking */
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($booking->status === BookingStatus::CommissionPaid) {
                return; // déjà réglé : ne pas enregistrer un PaymentFailed contradictoire
            }

            if ($this->hasExistingFinancialEvent($booking->id, FinancialEventType::PaymentFailed, $fedapayRef)) {
                return;
            }

            $this->recordFinancialEvent(
                FinancialEventType::PaymentFailed,
                $booking,
                (int) $booking->commission_ugc,
                ['fedapay_ref' => $fedapayRef, 'status' => 'failed', 'metadata' => ['kind' => 'ugc_commission', 'reason' => $reason]],
            );
        });
    }

    /**
     * Fallback reconciliation: poll FedaPay and settle if approved (webhook lag).
     */
    public function checkAndProcessBooking(Booking $booking): Booking
    {
        if ($booking->status === BookingStatus::CommissionPaid) {
            $this->lastCommissionPaymentStatus = 'paid';

            return $booking;
        }

        if ($booking->status !== BookingStatus::Pending || ! $booking->fedapay_transaction_id) {
            $this->lastCommissionPaymentStatus = 'pending';

            return $booking;
        }

        $transaction = $this->fedapayService->retrieveTransaction((int) $booking->fedapay_transaction_id);

        if ($transaction->status === 'approved') {
            $this->lastCommissionPaymentStatus = 'paid';

            return $this->markBookingCommissionPaid($booking, (string) ($transaction->reference ?? 'fedapay_poll'));
        }

        $this->lastCommissionPaymentStatus = in_array($transaction->status, self::TERMINAL_FAILED_STATUSES, true)
            ? 'failed'
            : 'pending';

        return $booking;
    }

    // -------------------------------------------------------------------------
    // Mission candidature hybrid per-Face settlement (ugc-8-4, escrow par-Candidature)
    // -------------------------------------------------------------------------

    /**
     * Initiate the hybrid per-Face settlement when a Producer accepts a UGC mission
     * candidature (D-8.4.e). Mirror of initiateForBooking but owner = Candidature and
     * escrow per-Candidature (mécanisme #2, PARENTLESS mission_payment_candidatures).
     *
     * Under a mission row lock + transaction: re-checks capacity (engaged + in-flight
     * reserved), creates a PARENTLESS escrow entry (Pending, montant_face_recoit =
     * faceReceives), initiates a FedaPay checkout for totalProducerPays (cash + 10 %
     * service fee), and stamps the transaction id on the entry. The candidature stays
     * `pending` — it only flips to `accepted` when the webhook confirms the payment
     * (markUgcMissionCandidaturePaid). A FedaPay failure rolls the whole transaction
     * back, so no orphan entry is left.
     *
     * Outcomes (the controller maps them, calque produit-seul accept) :
     *  - 'initiated' → 200 with checkout_url ;
     *  - 'full'      → 422 MISSION_FULL (capacité engagés + in-flight atteinte) ;
     *  - 'already'   → 422 (candidature plus pending ou déjà réglée).
     *
     * @return array{outcome: 'initiated', candidature: Candidature, checkout_url: string}
     *                                                                                     |array{outcome: 'full'|'already'}
     *
     * @throws ValidationException
     */
    public function initiateForUgcMissionCandidature(Candidature $candidature): array
    {
        $candidature->loadMissing('mission', 'face');
        $mission = $candidature->mission;
        $face = $candidature->face;

        if (! $mission instanceof Mission || ! $face instanceof Face) {
            throw ValidationException::withMessages([
                'candidature' => ['Candidature invalide pour le règlement hybride.'],
            ]);
        }

        $faceRate = $this->faceEntitlementService->capabilities($face)->commissionRate;
        $pricing = new BookingPricing((int) $mission->montant_remuneration, $faceRate);

        return DB::transaction(function () use ($candidature, $mission, $pricing): array {
            // Sérialise les accepts concurrents de la même mission (capacité, calque D-2.4.d).
            /** @var Mission $lockedMission */
            $lockedMission = Mission::query()->lockForUpdate()->findOrFail($mission->id);

            /** @var Candidature $locked */
            $locked = Candidature::query()->lockForUpdate()->findOrFail($candidature->id);
            if ($locked->status !== CandidatureStatus::Pending) {
                return ['outcome' => 'already'];
            }

            // Ré-initiation idempotente (double-clic avant le webhook) : la candidature a
            // déjà une entry in-flight → régénère le checkout existant, pas de 2ᵉ entry
            // (unique(candidature_id)) ni de 2ᵉ transaction FedaPay.
            /** @var MissionPaymentCandidature|null $existingEntry */
            $existingEntry = MissionPaymentCandidature::query()
                ->where('candidature_id', $locked->id)
                ->lockForUpdate()
                ->first();

            if ($existingEntry !== null) {
                if ($existingEntry->escrow_status === EscrowStatus::Pending
                    && $existingEntry->fedapay_transaction_id !== null) {
                    $regenerated = $this->fedapayService->regenerateTokenForTransaction(
                        (int) $existingEntry->fedapay_transaction_id
                    );

                    return ['outcome' => 'initiated', 'candidature' => $locked, 'checkout_url' => $regenerated['checkout_url']];
                }

                // Entry non-Pending (déjà locked/released/refunded) → candidature déjà réglée.
                return ['outcome' => 'already'];
            }

            if ($lockedMission->status !== MissionStatus::Published) {
                return ['outcome' => 'full']; // auto-fermée à capacité (Closed) ou état non engageable
            }

            // Capacité réservée = engagés + in-flight (D-8.4.e). Les engagés sont les
            // candidatures actives ; les in-flight = candidatures pending portant une entry
            // PARENTLESS escrow=Pending (un paiement initié mais pas encore confirmé).
            $engagedCount = Candidature::query()
                ->where('mission_id', $lockedMission->id)
                ->whereIn('status', [
                    CandidatureStatus::Accepted->value,
                    CandidatureStatus::Confirmed->value,
                    CandidatureStatus::InProgress->value,
                    CandidatureStatus::Completed->value,
                ])
                ->count();

            $inflightCount = Candidature::query()
                ->where('mission_id', $lockedMission->id)
                ->where('status', CandidatureStatus::Pending->value)
                ->whereHas('paymentEntry', function ($query): void {
                    $query->whereNull('mission_payment_id')
                        ->where('escrow_status', EscrowStatus::Pending->value);
                })
                ->count();

            if ($engagedCount + $inflightCount >= $lockedMission->nombre_faces_voulu) {
                return ['outcome' => 'full'];
            }

            // Entry PARENTLESS (D-8.4.a) — montant_face_recoit posé à la création (immuable).
            /** @var MissionPaymentCandidature $entry */
            $entry = MissionPaymentCandidature::create([
                'mission_payment_id' => null,
                'candidature_id' => $locked->id,
                'face_id' => $locked->face_id,
                'montant_face_recoit' => $pricing->faceReceives,
                'escrow_status' => EscrowStatus::Pending,
            ]);

            // FedaPay charge totalProducerPays (cash + 10 % frais service). L'appel HTTP est
            // VOLONTAIREMENT dans la transaction sous locks (Mission + Candidature + entry) :
            // un échec rollback tout (pas d'entry orpheline — D-8.4.e ; cleanup orphelin déféré
            // D-8.4.n). Tradeoff assumé (revue 2026-06-27, option 2) : le lock-row mission est
            // tenu le temps de l'aller-retour FedaPay — impact faible (lock par-mission, accepts
            // séquentiels par Producteur). Diverge ainsi de initiateForBooking (FedaPay hors
            // transaction sous Cache::lock) par ce choix d'atomicité. Le try/catch convertit une
            // panne FedaPay en 422 propre (au lieu d'un 500 brut) ; la ValidationException
            // propage hors de la closure ⇒ rollback ⇒ aucune entry orpheline.
            $idempotencyKey = Str::uuid()->toString();

            try {
                $result = $this->fedapayService->initiatePaymentForUgcMissionCandidature(
                    $locked,
                    $pricing->totalProducerPays,
                    $idempotencyKey,
                );
            } catch (\Throwable $e) {
                Log::warning('UGC hybride: initiation FedaPay échouée — règlement non initié', [
                    'candidature_id' => $locked->id,
                    'mission_id' => $mission->id,
                    'error' => $e->getMessage(),
                ]);

                throw ValidationException::withMessages([
                    'payment' => ['Le paiement du règlement est temporairement indisponible. Veuillez réessayer.'],
                ]);
            }

            $entry->update(['fedapay_transaction_id' => (string) $result['fedapay_transaction_id']]);

            return ['outcome' => 'initiated', 'candidature' => $locked, 'checkout_url' => $result['checkout_url']];
        });
    }

    // -------------------------------------------------------------------------
    // Mission commission (pending_payment → published) — NO FinancialEvent (D-1.5.g)
    // -------------------------------------------------------------------------

    /**
     * Initiate a FedaPay checkout for a UGC mission publication commission.
     * Idempotent (same reuse/regenerate/reset semantics as the booking path) but
     * WITHOUT any FinancialEvent (the audit trait is booking-scoped — D-1.5.g).
     *
     * @return array{mission: Mission, checkout_url: string}
     *
     * @throws ValidationException
     */
    public function initiateForMission(Mission $mission): array
    {
        $lock = Cache::lock("ugc_commission_mission_{$mission->id}", 30);

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'status' => ['Un paiement est deja en cours pour cette commission.'],
            ]);
        }

        try {
            /** @var Mission $mission */
            $mission = Mission::query()->findOrFail($mission->id);

            $this->assertMissionPayable($mission);

            if ($mission->fedapay_transaction_id !== null) {
                $existing = $this->fedapayService->retrieveTransaction((int) $mission->fedapay_transaction_id);

                if (! in_array($existing->status, self::TERMINAL_FAILED_STATUSES, true)) {
                    $regenerated = $this->fedapayService->regenerateTokenFromTransaction($existing);

                    return [
                        'mission' => $mission->fresh(),
                        'checkout_url' => $regenerated['checkout_url'],
                    ];
                }

                $mission->update(['fedapay_transaction_id' => null, 'payment_initiation_key' => null]);
                $mission = $mission->fresh();
            }

            $idempotencyKey = $mission->payment_initiation_key ?: Str::uuid()->toString();
            $mission->update(['payment_initiation_key' => $idempotencyKey]);

            $result = $this->fedapayService->initiatePaymentForUgcMission($mission->fresh(), $idempotencyKey);

            return DB::transaction(function () use ($mission, $result): array {
                /** @var Mission $locked */
                $locked = Mission::query()->lockForUpdate()->findOrFail($mission->id);

                if ($locked->fedapay_transaction_id !== null) {
                    return ['mission' => $locked->fresh(), 'checkout_url' => $result['checkout_url']];
                }

                $locked->update([
                    'fedapay_transaction_id' => $result['fedapay_transaction_id'],
                    'payment_initiation_key' => null,
                ]);

                return ['mission' => $locked->fresh(), 'checkout_url' => $result['checkout_url']];
            });
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Settle an approved UGC mission commission: pending_payment → published.
     * Idempotent on `status === Published`. No FinancialEvent (D-1.5.g): audit is
     * `commission_paid_at` + `fedapay_transaction_id`. A payment landing on a
     * non-UGC or non-pending_payment mission is logged for ops and no-oped —
     * never thrown — so the webhook job is never poisoned into a retry storm.
     */
    public function markMissionCommissionPaid(Mission $mission, string $fedapayRef): Mission
    {
        return DB::transaction(function () use ($mission, $fedapayRef) {
            /** @var Mission $mission */
            $mission = Mission::query()->lockForUpdate()->findOrFail($mission->id);

            if ($mission->type_mission !== MissionType::Ugc) {
                // Defense-in-depth : ce chemin ne doit JAMAIS publier une mission
                // standard (escrow). Disjonction déjà garantie par le filtre type du
                // lookup webhook — garde paranoïaque, no-op (jamais throw).
                Log::critical('UGC: settlement publication appelé sur une mission non-UGC — ignoré', [
                    'mission_id' => $mission->id,
                    'type_mission' => $mission->type_mission->value,
                    'fedapay_ref' => $fedapayRef,
                ]);

                return $mission;
            }

            if ($mission->status === MissionStatus::Published) {
                return $mission; // idempotent
            }

            if ($mission->status !== MissionStatus::PendingPayment) {
                // Statut terminal : paiement arrivé sur une mission non-publiable.
                // NE PAS throw (poison webhook) — journaliser pour l'ops (refund = 2.5).
                Log::critical('UGC: paiement reçu sur une mission non-publiable — remboursement ops requis', [
                    'mission_id' => $mission->id,
                    'status' => $mission->status->value,
                    'fedapay_ref' => $fedapayRef,
                ]);

                return $mission;
            }

            $mission->update([
                'status' => MissionStatus::Published,
                'commission_paid_at' => now(),
            ]);

            Log::info('UGC mission commission paid — mission published', [
                'mission_id' => $mission->id,
                'fedapay_ref' => $fedapayRef,
            ]);

            return $mission->fresh();
        });
    }

    /**
     * Record a failed/cancelled UGC mission commission attempt (log only).
     * Status is unchanged (stays pending_payment) so the producer can retry.
     */
    public function markMissionCommissionFailed(Mission $mission, string $fedapayRef, string $reason): void
    {
        Log::info('UGC mission commission payment failed — mission stays pending_payment', [
            'mission_id' => $mission->id,
            'fedapay_ref' => $fedapayRef,
            'reason' => $reason,
            'status' => $mission->status->value,
        ]);
    }

    /**
     * Fallback reconciliation: poll FedaPay and publish if approved (webhook lag).
     */
    public function checkAndProcessMission(Mission $mission): Mission
    {
        if ($mission->status === MissionStatus::Published) {
            $this->lastCommissionPaymentStatus = 'paid';

            return $mission;
        }

        if ($mission->status !== MissionStatus::PendingPayment || ! $mission->fedapay_transaction_id) {
            $this->lastCommissionPaymentStatus = 'pending';

            return $mission;
        }

        $transaction = $this->fedapayService->retrieveTransaction((int) $mission->fedapay_transaction_id);

        if ($transaction->status === 'approved') {
            $this->lastCommissionPaymentStatus = 'paid';

            return $this->markMissionCommissionPaid($mission, (string) ($transaction->reference ?? 'fedapay_poll'));
        }

        $this->lastCommissionPaymentStatus = in_array($transaction->status, self::TERMINAL_FAILED_STATUSES, true)
            ? 'failed'
            : 'pending';

        return $mission;
    }

    /**
     * Dernier statut de paiement de commission calculé par checkAndProcess* (ugc-3-5 Item 3, lecture seule).
     */
    public function lastCommissionPaymentStatus(): string
    {
        return $this->lastCommissionPaymentStatus;
    }

    // -------------------------------------------------------------------------
    // Guards
    // -------------------------------------------------------------------------

    /**
     * @throws ValidationException
     */
    private function assertBookingPayable(Booking $booking): void
    {
        if ($booking->type_contenu !== 'UGC') {
            throw ValidationException::withMessages([
                'status' => ['Ce booking n\'est pas une dotation UGC.'],
            ]);
        }

        if ($booking->status !== BookingStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => ['La commission de ce booking ne peut pas être payée dans son état actuel.'],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertMissionPayable(Mission $mission): void
    {
        if ($mission->type_mission !== MissionType::Ugc) {
            throw ValidationException::withMessages([
                'status' => ['Cette mission n\'est pas une dotation UGC.'],
            ]);
        }

        if ($mission->status !== MissionStatus::PendingPayment) {
            throw ValidationException::withMessages([
                'status' => ['La commission de cette mission ne peut pas être payée dans son état actuel.'],
            ]);
        }
    }
}
