<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Enums\BookingStatus;
use App\Enums\UgcTunnelStatus;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use App\Support\BusinessTimeSql;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filtre « À traiter » de la liste des bookings : ceux où l'utilisateur connecté doit agir
 * MAINTENANT (fenêtre d'action encore ouverte). Source unique du filtre `status=to_process`
 * et du tri par défaut « à traiter d'abord » de GET /bookings.
 *
 * Face : demande cash à répondre, demande UGC à répondre, fin de prestation à confirmer,
 * réception UGC à confirmer, vidéo UGC à envoyer, contestation d'absence / d'annulation tardive.
 * Producer : booking accepté à payer, commission UGC à payer, fin de prestation à confirmer,
 * envoi UGC à confirmer, livrable UGC à valider.
 *
 * Les conditions reprennent les règles des policies et des échéances du modèle Booking
 * (faceResponseDeadline, paymentDeadline, ugcCommissionPaymentDeadline…), traduites en SQL.
 *
 * @mixin Booking
 */
trait ScopesBookingsToProcess
{
    /** `BINARY` : aligne la collation SQL (_ci) sur le `=== 'UGC'` PHP (cf. ExpireUnacceptedUgcDealsCommand). */
    private const UGC_SQL = "BINARY type_contenu = 'UGC'";

    private const CASH_SQL = "NOT (BINARY type_contenu <=> 'UGC')";

    /**
     * Bookings où `$user` doit agir maintenant. Un utilisateur ni Face ni Producer n'a rien à traiter.
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeToProcess(Builder $query, User $user): void
    {
        $query->where(function (Builder $root) use ($user): void {
            if ($user->userable_type === Face::class) {
                $root->where('face_id', $user->id)
                    ->where(fn (Builder $cases) => self::faceCasesToProcess($cases, now()));
            } elseif ($user->userable_type === Producer::class) {
                $root->where('producer_id', $user->id)
                    ->where(fn (Builder $cases) => self::producerCasesToProcess($cases, now()));
            } else {
                $root->whereRaw('1 = 0');
            }
        });
    }

    /**
     * Tri « à traiter d'abord » : les bookings à traiter en tête, par échéance la plus proche
     * (sans échéance en dernier du groupe) ; les autres ne sont pas départagés ici (l'appelant
     * enchaîne `updated_at desc`).
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeOrderToProcessFirst(Builder $query, User $user): void
    {
        $toProcess = Booking::query()->toProcess($user)->select('bookings.id');
        $deadline = self::toProcessDeadlineSql();

        $query->orderByRaw(
            "CASE WHEN bookings.id IN ({$toProcess->toSql()}) "
            ."THEN COALESCE({$deadline}, CAST('9998-12-31 00:00:00' AS DATETIME)) "
            ."ELSE CAST('9999-12-31 00:00:00' AS DATETIME) END ASC",
            $toProcess->getBindings(),
        );
    }

    /**
     * @param  Builder<Booking>  $cases
     */
    private static function faceCasesToProcess(Builder $cases, CarbonInterface $now): void
    {
        $ugcDays = (int) config('ugc.acceptance_window_days', 7);

        $cases
            // Demande cash à répondre : échéance (création + 72 h, ou 08 h le jour du tournage) non écoulée.
            ->orWhere(fn (Builder $b) => $b
                ->where('status', BookingStatus::Pending->value)
                ->whereRaw(self::CASH_SQL)
                ->where('created_at', '>', $now->copy()->subHours(Booking::FACE_RESPONSE_WINDOW_HOURS))
                ->where(fn (Builder $d) => $d
                    ->whereNull('date_debut')
                    ->orWhereRaw(
                        BusinessTimeSql::localInstant('date_debut', Booking::FACE_RESPONSE_SHOOTING_DAY_HOUR).' > ?',
                        [$now],
                    )))
            // Demande UGC à répondre : commission réglée, fenêtre d'acceptation ouverte, non remboursée.
            ->orWhere(fn (Builder $b) => $b
                ->where('status', BookingStatus::CommissionPaid->value)
                ->whereRaw(self::UGC_SQL)
                ->whereNotNull('commission_paid_at')
                ->where('commission_paid_at', '>', $now->copy()->subDays($ugcDays))
                ->whereNull('commission_refunded_at'))
            // Fin de prestation à confirmer : tournage commencé (payé) ou déjà confirmé par le Producteur.
            ->orWhere(fn (Builder $b) => $b
                ->whereRaw(self::CASH_SQL)
                ->where(fn (Builder $s) => $s
                    ->where(fn (Builder $paid) => $paid
                        ->where('status', BookingStatus::Paid->value)
                        ->where('date_debut', '<=', $now))
                    ->orWhere('status', BookingStatus::ConfirmedByProducer->value)))
            // Tunnel UGC : réception à confirmer (expédié), vidéo à envoyer (reçu / avis attendu).
            ->orWhere(fn (Builder $b) => $b
                ->where('status', BookingStatus::Accepted->value)
                ->whereRaw(self::UGC_SQL)
                ->whereHas('shipment', fn (Builder $s) => $s->whereIn('tunnel_status', [
                    UgcTunnelStatus::Shipped->value,
                    UgcTunnelStatus::Received->value,
                    UgcTunnelStatus::AvisPending->value,
                ])))
            // Absence / annulation tardive contestable : escrow bloqué, délai de contestation ouvert.
            ->orWhere(fn (Builder $b) => $b
                ->pendingSettlement()
                ->where('settlement_due_at', '>', $now)
                ->whereNull('disputed_at'));
    }

    /**
     * @param  Builder<Booking>  $cases
     */
    private static function producerCasesToProcess(Builder $cases, CarbonInterface $now): void
    {
        $cases
            // Booking cash accepté à payer : fenêtre de paiement (acceptation + 24 h) ouverte.
            ->orWhere(fn (Builder $b) => $b
                ->where('status', BookingStatus::Accepted->value)
                ->whereRaw(self::CASH_SQL)
                ->whereNotNull('accepted_at')
                ->where('accepted_at', '>', $now->copy()->subHours(Booking::PAYMENT_WINDOW_HOURS)))
            // Commission UGC à payer : fenêtre (création + 48 h) ouverte.
            ->orWhere(fn (Builder $b) => $b
                ->where('status', BookingStatus::Pending->value)
                ->whereRaw(self::UGC_SQL)
                ->whereNull('commission_paid_at')
                ->where('created_at', '>', $now->copy()->subHours(Booking::UGC_COMMISSION_PAYMENT_WINDOW_HOURS)))
            // Fin de prestation à confirmer : tournage commencé (payé) ou déjà confirmé par la Face.
            ->orWhere(fn (Builder $b) => $b
                ->whereRaw(self::CASH_SQL)
                ->where(fn (Builder $s) => $s
                    ->where(fn (Builder $paid) => $paid
                        ->where('status', BookingStatus::Paid->value)
                        ->where('date_debut', '<=', $now))
                    ->orWhere('status', BookingStatus::ConfirmedByFace->value)))
            // Envoi UGC à confirmer : deal accepté, aucun colis déclaré.
            ->orWhere(fn (Builder $b) => $b
                ->where('status', BookingStatus::Accepted->value)
                ->whereRaw(self::UGC_SQL)
                ->whereDoesntHave('shipment'))
            // Livrable UGC à valider : vidéo en revue (Unboxing ou Avis).
            ->orWhere(fn (Builder $b) => $b
                ->where('status', BookingStatus::Accepted->value)
                ->whereRaw(self::UGC_SQL)
                ->whereHas('shipment', fn (Builder $s) => $s->whereIn('tunnel_status', [
                    UgcTunnelStatus::UnboxingInReview->value,
                    UgcTunnelStatus::AvisInReview->value,
                ])));
    }

    /**
     * Échéance d'action (instant UTC) selon le statut, NULL quand le cas n'en a pas (confirmation,
     * tunnel UGC). Toutes les valeurs sont des constantes entières : aucune saisie dans le SQL.
     */
    private static function toProcessDeadlineSql(): string
    {
        $responseHours = (int) Booking::FACE_RESPONSE_WINDOW_HOURS;
        $paymentHours = (int) Booking::PAYMENT_WINDOW_HOURS;
        $commissionHours = (int) Booking::UGC_COMMISSION_PAYMENT_WINDOW_HOURS;
        $ugcDays = (int) config('ugc.acceptance_window_days', 7);
        $cashResponse = "DATE_ADD(bookings.created_at, INTERVAL {$responseHours} HOUR)";
        $shootingDay = BusinessTimeSql::localInstant('bookings.date_debut', Booking::FACE_RESPONSE_SHOOTING_DAY_HOUR);
        $cash = str_replace('type_contenu', 'bookings.type_contenu', self::CASH_SQL);

        return 'CASE '
            ."WHEN bookings.status = 'pending' AND {$cash} "
            ."THEN LEAST({$cashResponse}, COALESCE({$shootingDay}, {$cashResponse})) "
            ."WHEN bookings.status = 'pending' THEN DATE_ADD(bookings.created_at, INTERVAL {$commissionHours} HOUR) "
            ."WHEN bookings.status = 'accepted' AND {$cash} THEN DATE_ADD(bookings.accepted_at, INTERVAL {$paymentHours} HOUR) "
            ."WHEN bookings.status = 'commission_paid' THEN DATE_ADD(bookings.commission_paid_at, INTERVAL {$ugcDays} DAY) "
            ."WHEN bookings.status IN ('no_show', 'cancelled_by_producer') THEN bookings.settlement_due_at "
            .'END';
    }
}
