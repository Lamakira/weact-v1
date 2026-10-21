<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\CompensationType;
use App\Enums\DeliverableKind;
use App\Enums\DeliverableValidationStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Enums\UgcTunnelStatus;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\Shipment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;

/**
 * File « À faire » du dashboard Face : ce que la Face doit traiter maintenant,
 * triée par urgence. Données de la Face uniquement ; aucune donnée Producteur
 * au-delà de son nom d'affichage, aucun montant autre que ce que la Face reçoit.
 *
 * Ordre : d'abord les items urgents (échéance < 48 h), par échéance croissante ;
 * puis les autres par type (RANK_*), à échéance égale par échéance croissante.
 */
class FaceDashboardTodoService
{
    public const MAX_ITEMS = 8;

    /** Seuil d'urgence d'une échéance, en heures. */
    private const URGENT_HOURS = 48;

    /** Ancienneté (jours) à partir de laquelle une candidature sans réponse remonte. */
    private const STALE_CANDIDATURE_DAYS = 5;

    private const RANK_URGENT = 0;

    private const RANK_PROPOSAL = 1;

    private const RANK_CONFIRM = 2;

    private const RANK_UGC = 3;

    private const RANK_CANDIDATURES = 4;

    /** Notation facultative et sans relance : juste avant le profil. */
    private const RANK_RATE = 5;

    private const RANK_PROFILE = 6;

    /** Durée (jours) pendant laquelle une mission/un booking terminé reste proposé à la notation. */
    private const RATE_WINDOW_DAYS = 7;

    /**
     * Catégorie de chaque type d'entrée (compteurs et badges). Un type absent de la table
     * retombe sur `other`.
     */
    private const CATEGORIES = [
        'booking_proposal' => 'bookings',
        'ugc_proposal' => 'bookings',
        'no_show_contest' => 'bookings',
        'late_cancellation_contest' => 'bookings',
        'confirm_prestation' => 'bookings',
        'mission_absence_contest' => 'missions',
        'confirm_participation' => 'candidatures',
        'ugc_reconfirm' => 'candidatures',
        'pending_candidatures' => 'candidatures',
        'ugc_deliverable' => 'livrables',
        'ugc_receipt' => 'livrables',
        'rate' => 'other',
        'profile_completion' => 'other',
    ];

    /**
     * File « À faire » plafonnée à MAX_ITEMS, sans `category`.
     *
     * @return list<array{type: string, title: string, meta: string|null, urgent_meta: string|null, action_label: string, url: string}>
     */
    public function forFace(User $user, Face $face): array
    {
        $items = array_map(
            function (array $item): array {
                unset($item['category']);

                return $item;
            },
            $this->allForFace($user, $face),
        );

        return array_slice($items, 0, self::MAX_ITEMS);
    }

    /**
     * Toutes les entrées, non tronquées et dans le même ordre que `forFace()`, avec leur
     * `category` (missions | candidatures | bookings | livrables | other) pour les compteurs.
     *
     * @return list<array{type: string, title: string, meta: string|null, urgent_meta: string|null, action_label: string, url: string, category: string}>
     */
    public function allForFace(User $user, Face $face): array
    {
        return collect([
            ...$this->bookingEntries($user),
            ...$this->missionAbsenceEntries($face),
            ...$this->candidatureConfirmationEntries($face),
            ...$this->ugcReceiptEntries($user, $face),
            ...$this->ugcTunnelEntries($user, $face),
            ...$this->staleCandidatureEntries($face),
            ...$this->rateEntries($user, $face),
            ...$this->profileEntries($face),
        ])
            ->sortBy([['rank', 'asc'], ['at', 'asc']])
            ->map(function (array $entry): array {
                $item = $entry['item'];
                $item['category'] = self::CATEGORIES[$item['type']] ?? 'other';

                return $item;
            })
            ->values()
            ->all();
    }

    /**
     * Propositions à traiter, absences contestables, prestations à confirmer.
     *
     * @return list<array{rank: int, at: int, item: array<string, mixed>}>
     */
    private function bookingEntries(User $user): array
    {
        $now = now();
        $todayBusiness = Carbon::now((string) config('app.business_timezone'))->toDateString();
        $cutoff = $now->copy()->subDays((int) config('ugc.acceptance_window_days', 7));

        $bookings = Booking::query()
            ->where('face_id', $user->id)
            ->where(function (Builder $q) use ($now, $todayBusiness, $cutoff): void {
                // Proposition cash en attente de réponse (expire à date_debut).
                $q->where(fn (Builder $b) => $b
                    ->where('status', BookingStatus::Pending->value)
                    ->where('date_debut', '>', $now)
                    ->whereRaw("NOT (BINARY type_contenu <=> 'UGC')"))
                    // Proposition UGC : commission payée, fenêtre d'acceptation ouverte.
                    ->orWhere(fn (Builder $b) => $b
                        ->where('status', BookingStatus::CommissionPaid->value)
                        ->whereRaw("BINARY type_contenu = 'UGC'")
                        ->whereNotNull('commission_paid_at')
                        ->where('commission_paid_at', '>', $cutoff)
                        ->whereNull('commission_refunded_at'))
                    // Absence signalée ou annulation tardive du Producteur, fenêtre de contestation ouverte.
                    ->orWhere(fn (Builder $b) => $b
                        ->whereIn('status', [BookingStatus::NoShow->value, BookingStatus::CancelledByProducer->value])
                        ->where('settlement_due_at', '>', $now)
                        ->whereNull('disputed_at')
                        ->whereNull('dispute_resolved_at'))
                    // Jour de tournage passé, prestation non confirmée par la Face.
                    ->orWhere(fn (Builder $b) => $b
                        ->whereIn('status', [BookingStatus::Paid->value, BookingStatus::ConfirmedByProducer->value])
                        ->whereRaw("NOT (BINARY type_contenu <=> 'UGC')")
                        ->whereNotNull('date_fin')
                        ->whereDate('date_fin', '<', $todayBusiness)
                        ->whereNull('face_confirmed_at'));
            })
            ->with('producer.userable')
            ->get();

        $entries = [];

        foreach ($bookings as $booking) {
            $producerName = $this->producerName($booking->producer?->userable);
            $url = "/face/bookings/{$booking->uuid}";

            $entry = match ($booking->status) {
                BookingStatus::Pending => $this->proposalEntry($booking, $producerName, $url, $booking->date_debut),
                BookingStatus::CommissionPaid => $this->ugcProposalEntry($booking, $producerName, $url),
                BookingStatus::NoShow => $this->noShowEntry($booking, $producerName, $url),
                BookingStatus::CancelledByProducer => $this->noShowEntry($booking, $producerName, $url, late: true),
                BookingStatus::Paid, BookingStatus::ConfirmedByProducer => $this->confirmEntry($booking, $producerName, $url),
                default => null,
            };

            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Absences déclarées par un Producteur sur une mission (escrow Locked, fenêtre de
     * contestation 72 h après notified_at encore ouverte) : à contester depuis la
     * page mission. Même traitement urgent que l'absence booking (`no_show_contest`).
     *
     * @return list<array{rank: int, at: int, item: array<string, mixed>}>
     */
    private function missionAbsenceEntries(Face $face): array
    {
        $cutoff = now()->subHours(MissionPaymentCandidature::DISPUTE_WINDOW_HOURS);

        $entries = MissionPaymentCandidature::query()
            ->where('face_id', $face->id)
            ->where('escrow_status', EscrowStatus::Locked->value)
            ->where('attendance_status', AttendanceStatus::Absent->value)
            ->whereNotNull('notified_at')
            ->where('notified_at', '>', $cutoff)
            ->with('missionPayment.mission.producer')
            ->get();

        $items = [];

        foreach ($entries as $entry) {
            $mission = $entry->missionPayment?->mission;
            $deadline = $entry->contestDeadline();

            if ($mission === null || $deadline === null) {
                continue;
            }

            $items[] = [
                'rank' => self::RANK_URGENT,
                'at' => $deadline->getTimestamp(),
                'item' => [
                    'type' => 'mission_absence_contest',
                    'title' => 'Absence signalée par '.$this->producerName($mission->producer),
                    'meta' => $mission->titre,
                    'urgent_meta' => 'Contester avant le '.Booking::formatForBusiness($deadline, 'd/m \à H:i'),
                    'action_label' => 'Contester',
                    'url' => "/face/missions/{$mission->uuid}",
                ],
            ];
        }

        return $items;
    }

    /**
     * @return array{rank: int, at: int, item: array<string, mixed>}|null
     */
    private function proposalEntry(Booking $booking, string $producerName, string $url, ?CarbonInterface $expiresAt): ?array
    {
        if ($expiresAt === null) {
            return null;
        }

        $meta = [
            $booking->type_contenu,
            $this->shortDate($expiresAt),
            $this->formatAmount($booking->montant_face_recoit),
        ];

        return $this->deadlineEntry(
            type: 'booking_proposal',
            title: "Répondre au booking de {$producerName}",
            baseMeta: $meta,
            deadline: $expiresAt,
            urgentWording: 'expire dans',
            actionLabel: 'Répondre',
            url: $url,
            rank: self::RANK_PROPOSAL,
        );
    }

    /**
     * @return array{rank: int, at: int, item: array<string, mixed>}|null
     */
    private function ugcProposalEntry(Booking $booking, string $producerName, string $url): ?array
    {
        if ($booking->commission_paid_at === null) {
            return null;
        }

        $expiresAt = $booking->commission_paid_at->copy()
            ->addDays((int) config('ugc.acceptance_window_days', 7));

        return $this->deadlineEntry(
            type: 'ugc_proposal',
            title: "Répondre à la proposition UGC de {$producerName}",
            baseMeta: [$booking->nom_produit],
            deadline: $expiresAt,
            urgentWording: 'expire dans',
            actionLabel: 'Répondre',
            url: $url,
            rank: self::RANK_PROPOSAL,
        );
    }

    /**
     * @return array{rank: int, at: int, item: array<string, mixed>}|null
     */
    private function noShowEntry(Booking $booking, string $producerName, string $url, bool $late = false): ?array
    {
        if ($booking->settlement_due_at === null) {
            return null;
        }

        $meta = array_filter([
            $booking->type_contenu,
            $booking->date_debut !== null ? $this->shortDate($booking->date_debut) : null,
        ]);

        return [
            'rank' => self::RANK_URGENT,
            'at' => $booking->settlement_due_at->getTimestamp(),
            'item' => [
                'type' => $late ? 'late_cancellation_contest' : 'no_show_contest',
                'title' => $late ? "Annulation tardive par {$producerName}" : "Absence signalée par {$producerName}",
                'meta' => $meta === [] ? null : implode(' · ', $meta),
                'urgent_meta' => 'Contester avant le '.Booking::formatForBusiness($booking->settlement_due_at, 'd/m \à H:i'),
                'action_label' => 'Contester',
                'url' => $url,
            ],
        ];
    }

    /**
     * @return array{rank: int, at: int, item: array<string, mixed>}|null
     */
    private function confirmEntry(Booking $booking, string $producerName, string $url): ?array
    {
        if ($booking->date_fin === null) {
            return null;
        }

        $meta = array_filter([$booking->type_contenu, $this->shortDate($booking->date_fin)]);

        return [
            'rank' => self::RANK_CONFIRM,
            'at' => $booking->date_fin->getTimestamp(),
            'item' => [
                'type' => 'confirm_prestation',
                'title' => "Confirmer la prestation pour {$producerName}",
                'meta' => implode(' · ', $meta),
                'urgent_meta' => null,
                'action_label' => 'Confirmer',
                'url' => $url,
            ],
        ];
    }

    /**
     * Candidatures acceptées que la Face doit confirmer : mission standard payée
     * (`confirm`, échéance Candidature::confirmationDeadline()) ou UGC (`reconfirm`,
     * échéance Candidature::reconfirmDeadline()). Mêmes gardes que les endpoints.
     *
     * @return list<array{rank: int, at: int, item: array<string, mixed>}>
     */
    private function candidatureConfirmationEntries(Face $face): array
    {
        $candidatures = Candidature::query()
            ->where('face_id', $face->id)
            ->where('status', CandidatureStatus::Accepted->value)
            ->with(['mission', 'paymentEntry'])
            ->get();

        $entries = [];

        foreach ($candidatures as $candidature) {
            $mission = $candidature->mission;

            if ($mission === null) {
                continue;
            }

            if ($mission->type_mission === MissionType::Ugc) {
                // Hybride : reconfirmable seulement si l'escrow est verrouillé (garde de `reconfirm`).
                if ($mission->type_compensation === CompensationType::Hybrid
                    && $candidature->paymentEntry?->escrow_status !== EscrowStatus::Locked) {
                    continue;
                }

                $deadline = $candidature->reconfirmDeadline();
                $type = 'ugc_reconfirm';
                $title = 'Reconfirmer votre participation';
                $actionLabel = 'Reconfirmer';
            } else {
                // Cash : la sélection doit être payée (entrée d'escrow de la Face).
                if ($candidature->paymentEntry === null) {
                    continue;
                }

                $deadline = $candidature->confirmationDeadline();
                $type = 'confirm_participation';
                $title = 'Confirmer votre participation';
                $actionLabel = 'Confirmer';
            }

            // Échéance passée : la place est expirée par le cron horaire, rien n'est plus à faire.
            if ($deadline === null || $deadline->lte(now())) {
                continue;
            }

            $entries[] = $this->deadlineEntry(
                type: $type,
                title: $title,
                baseMeta: [$mission->titre],
                deadline: $deadline,
                urgentWording: 'reste',
                actionLabel: $actionLabel,
                url: "/face/missions/{$mission->uuid}",
                rank: self::RANK_CONFIRM,
            );
        }

        return $entries;
    }

    /**
     * Colis UGC expédié dont la Face doit confirmer la réception (démarre le chrono Unboxing).
     * Mêmes gardes que UgcShipmentService::markReceived (booking Accepted / candidature
     * Confirmed, pas de remboursement de commission en cours, paiement réglé).
     *
     * @return list<array{rank: int, at: int, item: array<string, mixed>}>
     */
    private function ugcReceiptEntries(User $user, Face $face): array
    {
        $awaitingReceipt = fn (Builder $q) => $q
            ->where('tunnel_status', UgcTunnelStatus::Shipped->value)
            ->whereNull('recu_le');

        $bookings = Booking::query()
            ->where('face_id', $user->id)
            ->where('status', BookingStatus::Accepted->value)
            ->whereRaw("BINARY type_contenu = 'UGC'")
            ->whereNull('commission_refund_requested_at')
            ->whereNull('commission_refunded_at')
            ->whereHas('shipment', $awaitingReceipt)
            ->with(['shipment', 'producer.userable'])
            ->get();

        $candidatures = Candidature::query()
            ->where('face_id', $face->id)
            ->where('status', CandidatureStatus::Confirmed->value)
            ->whereHas('shipment', $awaitingReceipt)
            ->whereHas('mission', fn (Builder $q) => $q
                ->where('type_mission', MissionType::Ugc->value)
                ->whereNull('commission_refund_requested_at')
                ->whereNull('commission_refunded_at'))
            ->with(['shipment', 'paymentEntry', 'mission.producer'])
            ->get();

        $entries = [];

        foreach ($bookings as $booking) {
            $entries[] = $this->receiptEntry(
                $booking->shipment,
                (string) ($booking->nom_produit ?? 'produit UGC'),
                $this->producerName($booking->producer?->userable),
                "/face/bookings/{$booking->uuid}",
            );
        }

        foreach ($candidatures as $candidature) {
            $mission = $candidature->mission;

            // Paiement réglé : hybride = escrow Locked, produit-seul = commission payée.
            $paymentSettled = $mission->type_compensation === CompensationType::Hybrid
                ? $candidature->paymentEntry?->escrow_status === EscrowStatus::Locked
                : $mission->commission_paid_at !== null;

            if (! $paymentSettled) {
                continue;
            }

            $entries[] = $this->receiptEntry(
                $candidature->shipment,
                (string) ($mission->nom_produit ?? $mission->titre),
                $this->producerName($mission->producer),
                "/face/missions/{$mission->uuid}",
            );
        }

        return array_values(array_filter($entries));
    }

    /**
     * @return array{rank: int, at: int, item: array<string, mixed>}|null
     */
    private function receiptEntry(?Shipment $shipment, string $label, string $producerName, string $url): ?array
    {
        if ($shipment === null) {
            return null;
        }

        return [
            'rank' => self::RANK_UGC,
            'at' => $shipment->shipped_at->getTimestamp(),
            'item' => [
                'type' => 'ugc_receipt',
                'title' => 'Confirmer la réception du produit',
                'meta' => implode(' · ', array_filter([$label, $producerName])),
                'urgent_meta' => null,
                'action_label' => 'Confirmer',
                'url' => $url,
            ],
        ];
    }

    /**
     * Missions et bookings terminés depuis 7 jours au plus que la Face n'a pas encore notés.
     * La notation est facultative et sans relance : l'entrée disparaît passé ce délai.
     * Le modèle ne porte pas de date de fin : `updated_at` (posé à la clôture) en tient lieu.
     *
     * @return list<array{rank: int, at: int, item: array<string, mixed>}>
     */
    private function rateEntries(User $user, Face $face): array
    {
        $since = now()->subDays(self::RATE_WINDOW_DAYS);

        $candidatures = Candidature::query()
            ->where('face_id', $face->id)
            ->where('status', CandidatureStatus::Completed->value)
            ->where('updated_at', '>=', $since)
            ->whereDoesntHave('ratings', fn (Builder $q) => $q->where('rater_id', $user->id))
            ->with('mission.producer')
            ->get();

        $bookings = Booking::query()
            ->where('face_id', $user->id)
            ->where('status', BookingStatus::Completed->value)
            ->where('updated_at', '>=', $since)
            ->whereDoesntHave('bookingRatings', fn (Builder $q) => $q->where('rater_id', $user->id))
            ->with('producer.userable')
            ->get();

        $entries = [];

        foreach ($candidatures as $candidature) {
            $mission = $candidature->mission;

            if ($mission === null) {
                continue;
            }

            $entries[] = $this->rateEntry(
                $this->producerName($mission->producer),
                $mission->titre,
                "/face/missions/{$mission->uuid}",
                $candidature->updated_at,
            );
        }

        foreach ($bookings as $booking) {
            $entries[] = $this->rateEntry(
                $this->producerName($booking->producer?->userable),
                $booking->type_contenu === 'UGC' ? (string) ($booking->nom_produit ?? 'UGC') : $booking->type_contenu,
                "/face/bookings/{$booking->uuid}",
                $booking->updated_at,
            );
        }

        return $entries;
    }

    /**
     * @return array{rank: int, at: int, item: array<string, mixed>}
     */
    private function rateEntry(string $producerName, ?string $meta, string $url, ?CarbonInterface $completedAt): array
    {
        return [
            'rank' => self::RANK_RATE,
            'at' => $completedAt?->getTimestamp() ?? 0,
            'item' => [
                'type' => 'rate',
                'title' => "Noter {$producerName}",
                'meta' => $meta === '' ? null : $meta,
                'urgent_meta' => null,
                'action_label' => 'Noter',
                'url' => $url,
            ],
        ];
    }

    /**
     * Livrables UGC (Unboxing / Avis) que la Face doit envoyer avant une échéance.
     *
     * Échéance Unboxing = recu_le + ugc.deliverable_days.unboxing ; Avis = validation
     * de l'Unboxing + ugc.deliverable_days.avis — mêmes règles que UgcDeadlineService,
     * recalculées ici depuis les relations préchargées (pas de requête par deal).
     *
     * @return list<array{rank: int, at: int, item: array<string, mixed>}>
     */
    private function ugcTunnelEntries(User $user, Face $face): array
    {
        $activeStatuses = [UgcTunnelStatus::Received->value, UgcTunnelStatus::AvisPending->value];
        $withShipment = fn (Builder $q) => $q->whereIn('tunnel_status', $activeStatuses);
        $validatedUnboxing = fn (Relation $q) => $q
            ->where('kind', DeliverableKind::Unboxing->value)
            ->where('validation_status', DeliverableValidationStatus::Validated->value);

        $bookings = Booking::query()
            ->where('face_id', $user->id)
            ->whereHas('shipment', $withShipment)
            ->with(['shipment', 'deliverables' => $validatedUnboxing, 'producer.userable'])
            ->get();

        $candidatures = Candidature::query()
            ->where('face_id', $face->id)
            ->whereHas('shipment', $withShipment)
            ->with(['shipment', 'deliverables' => $validatedUnboxing, 'mission.producer'])
            ->get();

        $entries = [];

        foreach ($bookings as $booking) {
            $entry = $this->tunnelEntry(
                $booking->shipment,
                $booking->deliverables->first()?->validated_at,
                (string) ($booking->nom_produit ?? 'produit UGC'),
                $this->producerName($booking->producer?->userable),
                "/face/bookings/{$booking->uuid}",
            );
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        foreach ($candidatures as $candidature) {
            $mission = $candidature->mission;
            $entry = $this->tunnelEntry(
                $candidature->shipment,
                $candidature->deliverables->first()?->validated_at,
                (string) ($mission->nom_produit ?? $mission->titre),
                $this->producerName($mission->producer),
                "/face/missions/{$mission->uuid}",
            );
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @return array{rank: int, at: int, item: array<string, mixed>}|null
     */
    private function tunnelEntry(
        ?Shipment $shipment,
        ?CarbonInterface $unboxingValidatedAt,
        string $label,
        string $producerName,
        string $url,
    ): ?array {
        if ($shipment === null) {
            return null;
        }

        if ($shipment->tunnel_status === UgcTunnelStatus::Received && $shipment->recu_le !== null) {
            $kind = 'Unboxing';
            $deadline = $shipment->recu_le->copy()->addDays((int) config('ugc.deliverable_days.unboxing', 7));
        } elseif ($shipment->tunnel_status === UgcTunnelStatus::AvisPending && $unboxingValidatedAt !== null) {
            $kind = 'Avis';
            $deadline = $unboxingValidatedAt->copy()->addDays((int) config('ugc.deliverable_days.avis', 14));
        } else {
            return null;
        }

        return $this->deadlineEntry(
            type: 'ugc_deliverable',
            title: "Envoyer la vidéo {$kind} — {$label}",
            baseMeta: [$producerName],
            deadline: $deadline,
            urgentWording: 'reste',
            actionLabel: 'Envoyer la vidéo',
            url: $url,
            rank: self::RANK_UGC,
            overdueWording: 'échéance dépassée',
        );
    }

    /**
     * Une seule entrée groupée : candidatures encore sans réponse après N jours.
     *
     * @return list<array{rank: int, at: int, item: array<string, mixed>}>
     */
    private function staleCandidatureEntries(Face $face): array
    {
        $candidatures = Candidature::query()
            ->where('face_id', $face->id)
            ->where('status', CandidatureStatus::Pending->value)
            ->where('created_at', '<=', now()->subDays(self::STALE_CANDIDATURE_DAYS))
            ->whereHas('mission', fn (Builder $q) => $q->where('status', MissionStatus::Published->value))
            ->with('mission:id,titre')
            ->orderBy('created_at')
            ->get();

        if ($candidatures->isEmpty()) {
            return [];
        }

        $count = $candidatures->count();
        $titles = $candidatures->map(fn (Candidature $c): ?string => $c->mission?->titre)->filter()->unique()->values();
        $shown = $titles->take(2)->implode(' · ');
        $extra = $titles->count() - 2;

        return [[
            'rank' => self::RANK_CANDIDATURES,
            'at' => $candidatures->first()->created_at?->getTimestamp() ?? 0,
            'item' => [
                'type' => 'pending_candidatures',
                'title' => $count === 1
                    ? '1 candidature sans réponse depuis plus de '.self::STALE_CANDIDATURE_DAYS.' jours'
                    : "{$count} candidatures sans réponse depuis plus de ".self::STALE_CANDIDATURE_DAYS.' jours',
                'meta' => $shown === '' ? null : $shown.($extra > 0 ? " · +{$extra}" : ''),
                'urgent_meta' => null,
                'action_label' => 'Voir',
                'url' => '/face/candidatures',
            ],
        ]];
    }

    /**
     * @return list<array{rank: int, at: int, item: array<string, mixed>}>
     */
    private function profileEntries(Face $face): array
    {
        $missing = count($face->profile_completion_missing);

        if ($missing === 0) {
            return [];
        }

        return [[
            'rank' => self::RANK_PROFILE,
            'at' => 0,
            'item' => [
                'type' => 'profile_completion',
                'title' => 'Compléter le profil',
                'meta' => "Profil à {$face->profile_completion_percentage} % · "
                    .($missing === 1 ? '1 élément manquant' : "{$missing} éléments manquants"),
                'urgent_meta' => null,
                'action_label' => 'Compléter',
                'url' => '/face/profile',
            ],
        ]];
    }

    /**
     * Entrée à échéance : urgente (< 48 h) => rang 0 et compte à rebours en
     * `urgent_meta` ; sinon rang du type et compte à rebours dans `meta`.
     *
     * @param  array<int, string|null>  $baseMeta
     * @return array{rank: int, at: int, item: array<string, mixed>}
     */
    private function deadlineEntry(
        string $type,
        string $title,
        array $baseMeta,
        CarbonInterface $deadline,
        string $urgentWording,
        string $actionLabel,
        string $url,
        int $rank,
        string $overdueWording = 'expire bientôt',
    ): array {
        $seconds = $deadline->getTimestamp() - now()->getTimestamp();
        $urgent = $seconds < self::URGENT_HOURS * 3600;
        $countdown = $seconds <= 0 ? $overdueWording : "{$urgentWording} ".$this->humanizeRemaining($seconds);

        $meta = array_filter($baseMeta, fn (?string $part): bool => $part !== null && $part !== '');

        if (! $urgent) {
            $meta[] = $countdown;
        }

        return [
            'rank' => $urgent ? self::RANK_URGENT : $rank,
            'at' => $deadline->getTimestamp(),
            'item' => [
                'type' => $type,
                'title' => $title,
                'meta' => $meta === [] ? null : implode(' · ', $meta),
                'urgent_meta' => $urgent ? $countdown : null,
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

    private function producerName(mixed $producer): string
    {
        return $producer instanceof Producer ? $producer->display_name : 'un Producteur';
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
