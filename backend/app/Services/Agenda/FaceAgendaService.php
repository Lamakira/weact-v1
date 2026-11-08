<?php

declare(strict_types=1);

namespace App\Services\Agenda;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Models\Booking;
use App\Models\EscrowTransaction;
use App\Models\Face;
use App\Models\FaceUnavailableDay;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\User;
use App\Services\DateChange\AgendaConflictChecker;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Calendrier d'une Face : jours bloqués par elle, bookings et missions engagés, en journées entières
 * du fuseau métier. `bookings.face_id` est un users.id ; `face_unavailable_days.face_id` un faces.id.
 */
class FaceAgendaService
{
    /** Horizon de blocage : aujourd'hui + 365 jours. */
    public const HORIZON_DAYS = 365;

    /** Statuts d'un booking affiché comme engagé (mêmes que Booking::scopeOverlappingActiveForFace). */
    private const ENGAGED_STATUSES = [
        BookingStatus::Accepted,
        BookingStatus::Paid,
        BookingStatus::ConfirmedByFace,
        BookingStatus::ConfirmedByProducer,
    ];

    public function __construct(private readonly AgendaConflictChecker $conflicts) {}

    /** Aujourd'hui en jour métier (`Y-m-d`). */
    public function today(): string
    {
        return Carbon::now($this->timezone())->toDateString();
    }

    /** Dernier jour modifiable (`Y-m-d`). */
    public function horizonEnd(): string
    {
        return Carbon::now($this->timezone())->addDays(self::HORIZON_DAYS)->toDateString();
    }

    // -------------------------------------------------------------------------
    // Lectures
    // -------------------------------------------------------------------------

    /**
     * Calendrier complet de la Face sur [from, to].
     *
     * @return array{from: string, to: string, today: string, unavailable_days: list<string>, events: list<array<string, mixed>>, upcoming_earnings: array{amount: int, count: int}}
     */
    public function agenda(User $faceUser, Face $face, string $from, string $to): array
    {
        $events = [];

        foreach ($this->bookingsOverlapping($faceUser, $from, $to, [BookingStatus::Pending, BookingStatus::Completed, ...self::ENGAGED_STATUSES]) as $booking) {
            $producerName = $this->producerName($booking);

            $events[] = [
                'kind' => 'booking',
                'uuid' => $booking->uuid,
                // Type de contenu demandé (« Clip vidéo », « Publicité »…) : le Producteur est déjà dans `counterpart`.
                'title' => $booking->type_contenu ?: 'Booking',
                'status' => $booking->status->value,
                'state' => match ($booking->status) {
                    BookingStatus::Pending => 'pending',
                    BookingStatus::Completed => 'done',
                    default => 'engaged',
                },
                'start' => $this->businessDay($booking->date_debut),
                'end' => $this->businessDay($booking->date_fin),
                'counterpart' => $producerName,
                'amount_face' => $this->positiveOrNull($booking->montant_face_recoit),
                'secured' => $booking->escrowTransaction?->status === EscrowStatus::Locked->value,
            ];
        }

        foreach ($this->engagedMissionEntries($face, $from, $to) as $entry) {
            $mission = $entry->candidature->mission;
            $day = $mission->date_tournage->toDateString();

            $events[] = [
                'kind' => 'mission',
                'uuid' => $mission->uuid,
                'title' => $mission->titre,
                'status' => $entry->candidature->status->value,
                'state' => 'engaged',
                'start' => $day,
                'end' => $day,
                'counterpart' => $mission->producer->display_name,
                'amount_face' => $this->positiveOrNull($entry->montant_face_recoit),
                // Les entrées engagées sont par définition en séquestre bloqué et non libérées.
                'secured' => true,
            ];
        }

        usort($events, fn (array $a, array $b): int => [$a['start'], $a['end'], $a['uuid']] <=> [$b['start'], $b['end'], $b['uuid']]);

        return [
            'from' => $from,
            'to' => $to,
            'today' => $this->today(),
            'unavailable_days' => $this->blockedDays($face->id, $from, $to),
            'events' => $events,
            'upcoming_earnings' => $this->upcomingEarnings($faceUser, $face),
        ];
    }

    /**
     * Montants nets encore en séquestre bloqué pour la Face, toutes dates confondues.
     *
     * @return array{amount: int, count: int}
     */
    private function upcomingEarnings(User $faceUser, Face $face): array
    {
        $bookings = EscrowTransaction::query()
            ->where('status', EscrowStatus::Locked->value)
            ->whereHas('booking', fn ($booking) => $booking->where('face_id', $faceUser->id))
            ->selectRaw('COALESCE(SUM(amount), 0) as total, COUNT(*) as n')
            ->toBase()
            ->first();

        $entries = MissionPaymentCandidature::query()
            ->where('face_id', $face->id)
            ->where('escrow_status', EscrowStatus::Locked->value)
            ->whereNull('freed_at')
            ->selectRaw('COALESCE(SUM(montant_face_recoit), 0) as total, COUNT(*) as n')
            ->toBase()
            ->first();

        return [
            'amount' => (int) $bookings?->total + (int) $entries?->total,
            'count' => (int) $bookings?->n + (int) $entries?->n,
        ];
    }

    /**
     * Vue Producteur : jours bloqués ∪ jours d'un booking actif ∪ jours de mission engagée, sans motif.
     *
     * @return array{from: string, to: string, today: string, unavailable_days: list<string>}
     */
    public function producerView(User $faceUser, Face $face, string $from, string $to): array
    {
        $days = array_fill_keys($this->blockedDays($face->id, $from, $to), true);

        foreach ($this->bookingsOverlapping($faceUser, $from, $to, self::ENGAGED_STATUSES) as $booking) {
            foreach ($this->daysOf($this->businessDay($booking->date_debut), $this->businessDay($booking->date_fin), $from, $to) as $day) {
                $days[$day] = true;
            }
        }

        foreach ($this->engagedMissionEntries($face, $from, $to) as $entry) {
            $days[$entry->candidature->mission->date_tournage->toDateString()] = true;
        }

        $sorted = array_keys($days);
        sort($sorted);

        return [
            'from' => $from,
            'to' => $to,
            'today' => $this->today(),
            'unavailable_days' => $sorted,
        ];
    }

    /**
     * Jours bloqués par la Face dans [from, to], triés.
     *
     * @return list<string>
     */
    public function blockedDays(int $faceId, string $from, string $to): array
    {
        return FaceUnavailableDay::query()
            ->where('face_id', $faceId)
            ->whereBetween('day', [$from, $to])
            ->orderBy('day')
            ->get()
            ->map(fn (FaceUnavailableDay $row): string => $row->day->toDateString())
            ->values()
            ->all();
    }

    /**
     * Premier jour bloqué par la Face dans [firstDay, lastDay] (bornes incluses), ou null.
     * Les deux bornes sont des instants ou des dates : elles sont converties en jour métier.
     */
    public function firstBlockedDay(int $faceId, CarbonInterface|string $firstDay, CarbonInterface|string|null $lastDay = null): ?string
    {
        $first = $this->toBusinessDay($firstDay);
        $last = $lastDay === null ? $first : $this->toBusinessDay($lastDay);

        return $this->blockedDays($faceId, $first, $last)[0] ?? null;
    }

    /** « 12 novembre » (français), pour les messages. */
    public function frenchDay(string $day): string
    {
        return Carbon::parse($day)->locale('fr')->translatedFormat('j F');
    }

    // -------------------------------------------------------------------------
    // Écriture
    // -------------------------------------------------------------------------

    /**
     * Bloque et débloque des jours, atomiquement : une date invalide et rien n'est écrit.
     *
     * @param  list<string>  $add
     * @param  list<string>  $remove
     * @return list<string> tous les jours bloqués entre aujourd'hui et aujourd'hui + 365
     *
     * @throws ValidationException
     */
    public function updateUnavailableDays(User $faceUser, Face $face, array $add, array $remove): array
    {
        $today = $this->today();
        $horizon = $this->horizonEnd();

        return DB::transaction(function () use ($faceUser, $face, $add, $remove, $today, $horizon): array {
            // Verrou sur la Face : deux mises à jour concurrentes du même calendrier se sérialisent.
            Face::query()->whereKey($face->id)->lockForUpdate()->first();

            $errors = [];

            foreach (['add' => $add, 'remove' => $remove] as $field => $dates) {
                foreach ($dates as $index => $day) {
                    if ($day < $today || $day > $horizon) {
                        $errors["{$field}.{$index}"] = ['Ce jour est passé ou trop lointain.'];
                    }
                }
            }

            foreach (array_intersect($add, $remove) as $index => $day) {
                $errors["add.{$index}"] = ['Une même date ne peut pas être ajoutée et retirée dans la même demande.'];
            }

            foreach ($add as $index => $day) {
                if (isset($errors["add.{$index}"])) {
                    continue;
                }

                if ($this->isEngaged($faceUser, $day)) {
                    $errors["add.{$index}"] = ["Le {$this->frenchDay($day)} est déjà engagé (booking ou mission)."];
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            foreach ($add as $day) {
                FaceUnavailableDay::query()->firstOrCreate(['face_id' => $face->id, 'day' => $day]);
            }

            if ($remove !== []) {
                FaceUnavailableDay::query()->where('face_id', $face->id)->whereIn('day', $remove)->delete();
            }

            return $this->blockedDays($face->id, $today, $horizon);
        });
    }

    // -------------------------------------------------------------------------
    // Interne
    // -------------------------------------------------------------------------

    /** Jour engagé : booking actif (sur son jour métier) ou mission engagée. */
    private function isEngaged(User $faceUser, string $day): bool
    {
        if ($this->conflicts->hasConflict((int) $faceUser->id, $day)) {
            return true;
        }

        // Garde-fou fuseau : un booking stocké à 23 h 30 UTC la veille tombe sur ce jour métier.
        return $this->bookingsOverlapping($faceUser, $day, $day, self::ENGAGED_STATUSES)->isNotEmpty();
    }

    /**
     * Bookings datés de la Face dont la plage (jours métier) chevauche [from, to], triés par début.
     *
     * @param  list<BookingStatus>  $statuses
     * @return Collection<int, Booking>
     */
    private function bookingsOverlapping(User $faceUser, string $from, string $to, array $statuses): Collection
    {
        $start = Carbon::parse($from, $this->timezone())->startOfDay()->utc();
        $end = Carbon::parse($to, $this->timezone())->endOfDay()->utc();

        return Booking::query()
            ->overlappingRangeForFace((int) $faceUser->id, $start, $end)
            ->whereIn('status', array_map(fn (BookingStatus $s): string => $s->value, $statuses))
            ->whereNotNull('date_debut')
            ->whereNotNull('date_fin')
            ->with(['producer.userable', 'escrowTransaction'])
            ->orderBy('date_debut')
            ->get();
    }

    /**
     * Missions engagées de la Face dont le tournage tombe dans [from, to].
     * Même définition que AgendaConflictChecker::hasConflict.
     *
     * @return Collection<int, MissionPaymentCandidature>
     */
    private function engagedMissionEntries(Face $face, string $from, string $to): Collection
    {
        return MissionPaymentCandidature::query()
            ->where('face_id', $face->id)
            ->where('escrow_status', EscrowStatus::Locked->value)
            ->whereNull('freed_at')
            ->whereHas('candidature', fn ($candidature) => $candidature
                ->whereIn('status', [
                    CandidatureStatus::Accepted->value,
                    CandidatureStatus::Confirmed->value,
                    CandidatureStatus::InProgress->value,
                ])
                ->whereHas('mission', fn ($mission) => $mission
                    ->whereDate('date_tournage', '>=', $from)
                    ->whereDate('date_tournage', '<=', $to)))
            ->with('candidature.mission.producer')
            ->get();
    }

    private function producerName(Booking $booking): string
    {
        $profile = $booking->producer?->userable;
        $name = $profile instanceof Producer ? trim($profile->display_name) : '';

        return $name !== '' ? $name : 'Producteur';
    }

    /**
     * Jours `Y-m-d` de [start, end] restreints à [from, to].
     *
     * @return list<string>
     */
    private function daysOf(string $start, string $end, string $from, string $to): array
    {
        $cursor = Carbon::parse(max($start, $from));
        $last = Carbon::parse(min($end, $to));
        $days = [];

        while ($cursor->lessThanOrEqualTo($last)) {
            $days[] = $cursor->toDateString();
            $cursor = $cursor->copy()->addDay();
        }

        return $days;
    }

    /** Instant stocké (UTC) vers jour métier `Y-m-d`. */
    private function businessDay(CarbonInterface $instant): string
    {
        return $instant->copy()->setTimezone($this->timezone())->toDateString();
    }

    private function positiveOrNull(?int $amount): ?int
    {
        return $amount !== null && $amount > 0 ? $amount : null;
    }

    /** Instant ou date saisie, lus comme à l'enregistrement (fuseau de l'application), puis convertis en jour métier. */
    private function toBusinessDay(CarbonInterface|string $value): string
    {
        return $this->businessDay($value instanceof CarbonInterface ? $value : Carbon::parse($value));
    }

    private function timezone(): string
    {
        return (string) config('app.business_timezone');
    }
}
