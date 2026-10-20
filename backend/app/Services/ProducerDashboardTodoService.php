<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\CompensationType;
use App\Enums\DeliverableValidationStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Deliverable;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\Producer;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * File « À faire » du dashboard Producteur : ce que le Producteur doit traiter maintenant,
 * triée par urgence. Données du Producteur uniquement (ses missions, ses bookings).
 *
 * Ordre : d'abord les items urgents (échéance < 48 h), par échéance croissante ;
 * puis les autres par type (RANK_*), à échéance égale par échéance croissante.
 *
 * Pas d'entrée « répondre à une contestation » : la contestation d'une absence ou d'une
 * annulation tardive est réservée à la Face (BookingPolicy::contest), le Producteur n'a
 * aucun endpoint de réponse (l'arbitrage est administratif).
 */
class ProducerDashboardTodoService
{
    public const MAX_ITEMS = 8;

    /** Seuil d'urgence d'une échéance, en heures. */
    private const URGENT_HOURS = 48;

    private const RANK_URGENT = 0;

    private const RANK_PAYMENT = 1;

    private const RANK_CANDIDATURES = 2;

    private const RANK_VALIDATION = 3;

    private const RANK_SHIPMENT = 4;

    /** Notation facultative et sans relance : en dernier. */
    private const RANK_RATE = 5;

    /** Durée (jours) pendant laquelle une mission/un booking terminé reste proposé à la notation. */
    private const RATE_WINDOW_DAYS = 7;

    /**
     * File « À faire » plafonnée à MAX_ITEMS, sans `category`.
     *
     * @return list<array{type: string, title: string, meta: string|null, urgent_meta: string|null, action_label: string, url: string}>
     */
    public function forProducer(User $user, Producer $producer): array
    {
        $items = array_map(
            function (array $item): array {
                unset($item['category']);

                return $item;
            },
            $this->allForProducer($user, $producer),
        );

        return array_slice($items, 0, self::MAX_ITEMS);
    }

    /**
     * Toutes les entrées, non tronquées et dans le même ordre que `forProducer()`, avec leur
     * `category` (missions | candidatures | bookings | livrables | other) pour les compteurs.
     *
     * @return list<array{type: string, title: string, meta: string|null, urgent_meta: string|null, action_label: string, url: string, category: string}>
     */
    public function allForProducer(User $user, Producer $producer): array
    {
        return collect([
            ...$this->candidatureEntries($producer),
            ...$this->missionPaymentEntries($producer),
            ...$this->bookingPaymentEntries($user),
            ...$this->attendanceEntries($producer),
            ...$this->deliverableEntries($user, $producer),
            ...$this->shipmentEntries($user, $producer),
            ...$this->bookingCompletionEntries($user),
            ...$this->rateEntries($user, $producer),
        ])
            ->sortBy([['rank', 'asc'], ['at', 'asc']])
            ->map(fn (array $entry): array => $entry['item'])
            ->values()
            ->all();
    }

    /**
     * Une entrée par mission publiée qui a des candidatures en attente de réponse
     * (même périmètre que CandidatureResponseDeadlineService::unansweredCandidatures).
     * Échéance = Mission::producerResponseDeadline() quand elle existe.
     *
     * @return list<array{rank: int, at: int, item: array<string, mixed>}>
     */
    private function candidatureEntries(Producer $producer): array
    {
        $counts = Candidature::query()
            ->where('status', CandidatureStatus::Pending->value)
            ->whereDoesntHave('paymentEntry', fn (Builder $q) => $q->where('escrow_status', EscrowStatus::Pending->value))
            ->whereHas('mission', fn (Builder $q) => $q
                ->where('producer_id', $producer->id)
                ->where('status', MissionStatus::Published->value))
            ->toBase()
            ->selectRaw('mission_id, COUNT(*) as total')
            ->groupBy('mission_id')
            ->pluck('total', 'mission_id');

        if ($counts->isEmpty()) {
            return [];
        }

        $entries = [];

        foreach (Mission::query()->whereIn('id', $counts->keys())->get() as $mission) {
            $count = (int) $counts[$mission->id];

            $entries[] = $this->entry(
                category: 'candidatures',
                type: 'candidatures_to_process',
                title: $count === 1 ? '1 candidature à traiter' : "{$count} candidatures à traiter",
                baseMeta: [$mission->titre],
                deadline: $mission->producerResponseDeadline(),
                urgentWording: 'reste',
                actionLabel: 'Traiter',
                url: "/producer/missions/{$mission->uuid}?tab=candidatures",
                rank: self::RANK_CANDIDATURES,
                overdueWording: 'échéance dépassée',
            );
        }

        return $entries;
    }

    /**
     * Missions `pending_payment` : sélection cash à payer (24 h après sa création) ou
     * commission de publication d'une mission UGC (sans échéance).
     *
     * @return list<array{rank: int, at: int, item: array<string, mixed>}>
     */
    private function missionPaymentEntries(Producer $producer): array
    {
        $missions = Mission::query()
            ->where('producer_id', $producer->id)
            ->where('status', MissionStatus::PendingPayment->value)
            ->with(['payment' => fn ($q) => $q->where('status', MissionPaymentStatus::Pending->value)])
            ->get();

        $entries = [];

        foreach ($missions as $mission) {
            $url = "/producer/missions/{$mission->uuid}";

            if ($mission->type_mission === MissionType::Ugc) {
                $entries[] = $this->entry(
                    category: 'missions',
                    type: 'ugc_mission_commission',
                    title: 'Régler la commission de la mission',
                    baseMeta: [$mission->titre],
                    deadline: null,
                    urgentWording: 'reste',
                    actionLabel: 'Payer',
                    url: $url,
                    rank: self::RANK_PAYMENT,
                );

                continue;
            }

            $payment = $mission->payment;

            if (! $payment instanceof MissionPayment || $payment->created_at === null) {
                continue;
            }

            $entries[] = $this->entry(
                category: 'missions',
                type: 'selection_payment',
                title: 'Payer la sélection de Faces',
                baseMeta: [$mission->titre],
                deadline: $payment->created_at->copy()->addHours(MissionPaymentService::SELECTION_PAYMENT_WINDOW_HOURS),
                urgentWording: 'reste',
                actionLabel: 'Payer',
                url: $url,
                rank: self::RANK_PAYMENT,
                overdueWording: 'échéance dépassée',
            );
        }

        return $entries;
    }

    /**
     * Bookings à payer : cash accepté dont la fenêtre de paiement est ouverte
     * (BookingPolicy::pay) ; commission d'une demande UGC encore `pending`
     * (BookingPolicy::payCommission).
     *
     * @return list<array{rank: int, at: int, item: array<string, mixed>}>
     */
    private function bookingPaymentEntries(User $user): array
    {
        $bookings = Booking::query()
            ->where('producer_id', $user->id)
            ->where(function (Builder $q): void {
                $q->where(fn (Builder $b) => $b
                    ->where('status', BookingStatus::Accepted->value)
                    ->whereRaw("NOT (BINARY type_contenu <=> 'UGC')")
                    ->whereNotNull('accepted_at'))
                    ->orWhere(fn (Builder $b) => $b
                        ->where('status', BookingStatus::Pending->value)
                        ->whereRaw("BINARY type_contenu = 'UGC'"));
            })
            ->with('face.userable')
            ->get();

        $entries = [];

        foreach ($bookings as $booking) {
            $faceName = $this->faceName($booking->face?->userable);
            $url = "/producer/bookings/{$booking->uuid}";

            if ($booking->status === BookingStatus::Pending) {
                $entries[] = $this->entry(
                    category: 'bookings',
                    type: 'ugc_booking_commission',
                    title: "Régler la commission de la demande UGC pour {$faceName}",
                    baseMeta: [$booking->nom_produit],
                    deadline: $booking->ugcCommissionPaymentDeadline(),
                    urgentWording: 'reste',
                    actionLabel: 'Payer',
                    url: $url,
                    rank: self::RANK_PAYMENT,
                    overdueWording: 'échéance dépassée',
                );

                continue;
            }

            // Fenêtre fermée : le paiement n'est plus possible (le cron expire le booking).
            if (! $booking->isPaymentWindowOpen()) {
                continue;
            }

            $entries[] = $this->entry(
                category: 'bookings',
                type: 'booking_payment',
                title: "Payer le booking de {$faceName}",
                baseMeta: [$booking->type_contenu, $this->formatAmount((int) $booking->montant_total_producteur)],
                deadline: $booking->paymentDeadline(),
                urgentWording: 'reste',
                actionLabel: 'Payer',
                url: $url,
                rank: self::RANK_PAYMENT,
            );
        }

        return $entries;
    }

    /**
     * Missions cash dont le tournage a eu lieu et dont des présences ne sont pas validées
     * (mêmes gardes que MissionAttendanceController : mission clôturée ou en validation de
     * présences, paiement confirmé, jour de tournage atteint).
     *
     * @return list<array{rank: int, at: int, item: array<string, mixed>}>
     */
    private function attendanceEntries(Producer $producer): array
    {
        $pendingEntries = fn (Builder $q) => $q
            ->where('escrow_status', EscrowStatus::Locked->value)
            ->where('attendance_status', AttendanceStatus::Pending->value)
            ->whereDoesntHave('candidature', fn (Builder $c) => $c->where('status', CandidatureStatus::Expired->value));

        $missions = Mission::query()
            ->where('producer_id', $producer->id)
            ->where('type_mission', '!=', MissionType::Ugc->value)
            ->whereIn('status', [MissionStatus::Closed->value, MissionStatus::PendingAttendanceValidation->value])
            ->whereNotNull('date_tournage')
            ->whereHas('payment', fn (Builder $q) => $q
                ->where('status', MissionPaymentStatus::Paid->value)
                ->whereHas('entries', $pendingEntries))
            ->with(['payment' => fn ($q) => $q->withCount(['entries as pending_entries_count' => $pendingEntries])])
            ->get();

        $entries = [];

        foreach ($missions as $mission) {
            if (! $mission->isAttendanceOpen()) {
                continue;
            }

            $count = (int) ($mission->payment?->getAttribute('pending_entries_count') ?? 0);

            if ($count === 0) {
                continue;
            }

            $shootingStart = $mission->shootingDayStartsAt();

            $entries[] = [
                'rank' => self::RANK_VALIDATION,
                'at' => $shootingStart?->getTimestamp() ?? 0,
                'item' => [
                    'type' => 'attendance_validation',
                    'category' => 'missions',
                    'title' => $count === 1 ? '1 présence à valider' : "{$count} présences à valider",
                    'meta' => $mission->titre,
                    'urgent_meta' => null,
                    'action_label' => 'Valider',
                    'url' => "/producer/missions/{$mission->uuid}?tab=presences",
                ],
            ];
        }

        return $entries;
    }

    /**
     * Livrables UGC `in_review`, une entrée par mission ou par booking. Échéance indicative =
     * plus ancienne soumission + délai de revue (ugc.deliverable_review_sla_hours).
     *
     * @return list<array{rank: int, at: int, item: array<string, mixed>}>
     */
    private function deliverableEntries(User $user, Producer $producer): array
    {
        $deliverables = Deliverable::query()
            ->where('validation_status', DeliverableValidationStatus::InReview->value)
            ->where(function (Builder $q) use ($user, $producer): void {
                $q->whereHasMorph('owner', [Booking::class], fn (Builder $b) => $b->where('producer_id', $user->id))
                    ->orWhereHasMorph('owner', [Candidature::class], fn (Builder $c) => $c
                        ->whereHas('mission', fn (Builder $m) => $m->where('producer_id', $producer->id)));
            })
            ->with(['owner' => function ($morphTo): void {
                $morphTo->morphWith([
                    Booking::class => ['face.userable'],
                    Candidature::class => ['mission'],
                ]);
            }])
            ->get();

        /** @var array<string, array{count: int, oldest: CarbonInterface|null, label: string, url: string}> $groups */
        $groups = [];

        foreach ($deliverables as $deliverable) {
            $owner = $deliverable->owner;

            if ($owner instanceof Booking) {
                $key = "booking:{$owner->id}";
                $label = $this->faceName($owner->face?->userable);
                $url = "/producer/bookings/{$owner->uuid}";
            } elseif ($owner instanceof Candidature && $owner->mission !== null) {
                $key = "mission:{$owner->mission_id}";
                $label = $owner->mission->titre;
                $url = "/producer/missions/{$owner->mission->uuid}?tab=livrables";
            } else {
                continue;
            }

            $groups[$key] ??= ['count' => 0, 'oldest' => null, 'label' => $label, 'url' => $url];
            $groups[$key]['count']++;

            $submittedAt = $deliverable->submitted_at;
            if ($submittedAt !== null && ($groups[$key]['oldest'] === null || $submittedAt->lt($groups[$key]['oldest']))) {
                $groups[$key]['oldest'] = $submittedAt;
            }
        }

        $entries = [];
        $slaHours = (int) config('ugc.deliverable_review_sla_hours', 48);

        foreach ($groups as $group) {
            $entries[] = $this->entry(
                category: 'livrables',
                type: 'ugc_deliverable_review',
                title: $group['count'] === 1 ? '1 livrable à valider' : "{$group['count']} livrables à valider",
                baseMeta: [$group['label']],
                deadline: $group['oldest']?->copy()->addHours($slaHours),
                urgentWording: 'reste',
                actionLabel: 'Valider',
                url: $group['url'],
                rank: self::RANK_VALIDATION,
                overdueWording: 'délai de revue dépassé',
            );
        }

        return $entries;
    }

    /**
     * Envoi du produit à confirmer : UGC accepté (booking) ou Face confirmée sur une mission
     * UGC, pas encore expédié. Mêmes gardes que UgcShipmentService::confirm (statut, pas de
     * remboursement de commission, paiement réglé). Une entrée par booking ; une par mission.
     *
     * @return list<array{rank: int, at: int, item: array<string, mixed>}>
     */
    private function shipmentEntries(User $user, Producer $producer): array
    {
        $bookings = Booking::query()
            ->where('producer_id', $user->id)
            ->where('status', BookingStatus::Accepted->value)
            ->whereRaw("BINARY type_contenu = 'UGC'")
            ->whereNull('commission_refund_requested_at')
            ->whereNull('commission_refunded_at')
            ->whereDoesntHave('shipment')
            ->with('face.userable')
            ->get();

        $candidatures = Candidature::query()
            ->where('status', CandidatureStatus::Confirmed->value)
            ->whereDoesntHave('shipment')
            ->whereHas('mission', fn (Builder $q) => $q
                ->where('producer_id', $producer->id)
                ->where('type_mission', MissionType::Ugc->value)
                ->whereNull('commission_refund_requested_at')
                ->whereNull('commission_refunded_at'))
            ->with(['mission', 'paymentEntry'])
            ->get();

        $entries = [];

        foreach ($bookings as $booking) {
            $entries[] = $this->shipmentEntry(
                'bookings',
                'Confirmer l\'envoi du produit',
                [$this->faceName($booking->face?->userable), $booking->nom_produit],
                "/producer/bookings/{$booking->uuid}",
                $booking->accepted_at?->getTimestamp() ?? 0,
            );
        }

        /** @var array<int, array{mission: Mission, count: int}> $perMission */
        $perMission = [];

        foreach ($candidatures as $candidature) {
            $mission = $candidature->mission;

            // Paiement réglé : hybride = escrow Locked, produit-seul = commission payée.
            $paymentSettled = $mission->type_compensation === CompensationType::Hybrid
                ? $candidature->paymentEntry?->escrow_status === EscrowStatus::Locked
                : $mission->commission_paid_at !== null;

            if (! $paymentSettled) {
                continue;
            }

            $perMission[$mission->id] ??= ['mission' => $mission, 'count' => 0];
            $perMission[$mission->id]['count']++;
        }

        foreach ($perMission as $group) {
            $count = $group['count'];

            $entries[] = $this->shipmentEntry(
                'livrables',
                $count === 1 ? '1 envoi de produit à confirmer' : "{$count} envois de produit à confirmer",
                [$group['mission']->titre],
                "/producer/missions/{$group['mission']->uuid}?tab=candidatures",
                0,
            );
        }

        return $entries;
    }

    /**
     * @param  array<int, string|null>  $meta
     * @return array{rank: int, at: int, item: array<string, mixed>}
     */
    private function shipmentEntry(string $category, string $title, array $meta, string $url, int $at): array
    {
        $meta = array_filter($meta, fn (?string $part): bool => $part !== null && $part !== '');

        return [
            'rank' => self::RANK_SHIPMENT,
            'at' => $at,
            'item' => [
                'type' => 'ugc_shipment',
                'category' => $category,
                'title' => $title,
                'meta' => $meta === [] ? null : implode(' · ', $meta),
                'urgent_meta' => null,
                'action_label' => 'Confirmer',
                'url' => $url,
            ],
        ];
    }

    /**
     * Fin de booking cash à confirmer : jour de tournage passé, payé ou confirmé par la Face
     * seulement (BookingPolicy::confirm côté Producteur). Même règle de date que la to-do Face.
     *
     * @return list<array{rank: int, at: int, item: array<string, mixed>}>
     */
    private function bookingCompletionEntries(User $user): array
    {
        $todayBusiness = Carbon::now((string) config('app.business_timezone'))->toDateString();

        $bookings = Booking::query()
            ->where('producer_id', $user->id)
            ->whereIn('status', [BookingStatus::Paid->value, BookingStatus::ConfirmedByFace->value])
            ->whereRaw("NOT (BINARY type_contenu <=> 'UGC')")
            ->whereNotNull('date_fin')
            ->whereDate('date_fin', '<', $todayBusiness)
            ->with('face.userable')
            ->get();

        $entries = [];

        foreach ($bookings as $booking) {
            $meta = array_filter([$booking->type_contenu, $this->shortDate($booking->date_fin)]);

            $entries[] = [
                'rank' => self::RANK_VALIDATION,
                'at' => $booking->date_fin->getTimestamp(),
                'item' => [
                    'type' => 'booking_completion',
                    'category' => 'bookings',
                    'title' => 'Confirmer la prestation de '.$this->faceName($booking->face?->userable),
                    'meta' => implode(' · ', $meta),
                    'urgent_meta' => null,
                    'action_label' => 'Confirmer',
                    'url' => "/producer/bookings/{$booking->uuid}",
                ],
            ];
        }

        return $entries;
    }

    /**
     * Faces à noter : candidatures terminées (une entrée par mission) et bookings terminés
     * depuis 7 jours au plus, sans note du Producteur. Notation facultative et sans relance.
     * Le modèle ne porte pas de date de fin : `updated_at` (posé à la clôture) en tient lieu.
     *
     * @return list<array{rank: int, at: int, item: array<string, mixed>}>
     */
    private function rateEntries(User $user, Producer $producer): array
    {
        $since = now()->subDays(self::RATE_WINDOW_DAYS);

        $candidatures = Candidature::query()
            ->where('status', CandidatureStatus::Completed->value)
            ->where('updated_at', '>=', $since)
            ->whereHas('mission', fn (Builder $q) => $q->where('producer_id', $producer->id))
            ->whereDoesntHave('ratings', fn (Builder $q) => $q->where('rater_id', $user->id))
            ->with('mission')
            ->get();

        $bookings = Booking::query()
            ->where('producer_id', $user->id)
            ->where('status', BookingStatus::Completed->value)
            ->where('updated_at', '>=', $since)
            ->whereDoesntHave('bookingRatings', fn (Builder $q) => $q->where('rater_id', $user->id))
            ->with('face.userable')
            ->get();

        /** @var array<int, array{mission: Mission, count: int, at: int}> $perMission */
        $perMission = [];

        foreach ($candidatures as $candidature) {
            $mission = $candidature->mission;

            if ($mission === null) {
                continue;
            }

            $perMission[$mission->id] ??= ['mission' => $mission, 'count' => 0, 'at' => PHP_INT_MAX];
            $perMission[$mission->id]['count']++;
            $perMission[$mission->id]['at'] = min($perMission[$mission->id]['at'], $candidature->updated_at?->getTimestamp() ?? 0);
        }

        $entries = [];

        foreach ($perMission as $group) {
            $count = $group['count'];

            $entries[] = $this->rateEntry(
                $count === 1 ? 'Noter 1 Face' : "Noter {$count} Faces",
                $group['mission']->titre,
                "/producer/missions/{$group['mission']->uuid}?tab=candidatures",
                $group['at'],
            );
        }

        foreach ($bookings as $booking) {
            $entries[] = $this->rateEntry(
                'Noter '.$this->faceName($booking->face?->userable),
                $booking->type_contenu === 'UGC' ? (string) ($booking->nom_produit ?? 'UGC') : $booking->type_contenu,
                "/producer/bookings/{$booking->uuid}",
                $booking->updated_at?->getTimestamp() ?? 0,
            );
        }

        return $entries;
    }

    /**
     * @return array{rank: int, at: int, item: array<string, mixed>}
     */
    private function rateEntry(string $title, ?string $meta, string $url, int $at): array
    {
        return [
            'rank' => self::RANK_RATE,
            'at' => $at,
            'item' => [
                'type' => 'rate',
                'category' => 'other',
                'title' => $title,
                'meta' => $meta === '' ? null : $meta,
                'urgent_meta' => null,
                'action_label' => 'Noter',
                'url' => $url,
            ],
        ];
    }

    /**
     * Entrée avec échéance optionnelle : urgente (< 48 h) => rang 0 et compte à rebours en
     * `urgent_meta` ; sinon rang du type et compte à rebours dans `meta`. Sans échéance :
     * rang du type, échéance de tri à 0 (en tête de son rang).
     *
     * @param  array<int, string|null>  $baseMeta
     * @return array{rank: int, at: int, item: array<string, mixed>}
     */
    private function entry(
        string $category,
        string $type,
        string $title,
        array $baseMeta,
        ?CarbonInterface $deadline,
        string $urgentWording,
        string $actionLabel,
        string $url,
        int $rank,
        string $overdueWording = 'expire bientôt',
    ): array {
        $meta = array_filter($baseMeta, fn (?string $part): bool => $part !== null && $part !== '');
        $urgentMeta = null;
        $urgent = false;

        if ($deadline !== null) {
            $seconds = $deadline->getTimestamp() - now()->getTimestamp();
            $urgent = $seconds < self::URGENT_HOURS * 3600;
            $countdown = $seconds <= 0 ? $overdueWording : "{$urgentWording} ".$this->humanizeRemaining($seconds);

            if ($urgent) {
                $urgentMeta = $countdown;
            } else {
                $meta[] = $countdown;
            }
        }

        return [
            'rank' => $urgent ? self::RANK_URGENT : $rank,
            'at' => $deadline?->getTimestamp() ?? 0,
            'item' => [
                'type' => $type,
                'category' => $category,
                'title' => $title,
                'meta' => $meta === [] ? null : implode(' · ', $meta),
                'urgent_meta' => $urgentMeta,
                'action_label' => $actionLabel,
                'url' => $url,
            ],
        ];
    }

    private function humanizeRemaining(int $seconds): string
    {
        if ($seconds < 3600) {
            return 'moins d\'1 h';
        }

        $hours = intdiv($seconds, 3600);

        return $hours < self::URGENT_HOURS ? "{$hours} h" : intdiv($hours, 24).' j';
    }

    private function faceName(mixed $face): string
    {
        return $face instanceof Face ? $face->display_name : 'une Face';
    }

    /** « 14 oct. » — jour de calendrier, sans conversion de fuseau (date stockée à 00:00 UTC). */
    private function shortDate(CarbonInterface $date): string
    {
        return $date->copy()->locale('fr')->translatedFormat('j M');
    }

    private function formatAmount(int $amount): string
    {
        return number_format($amount, 0, ',', ' ').' XOF';
    }
}
