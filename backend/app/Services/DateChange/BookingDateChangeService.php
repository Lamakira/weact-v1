<?php

declare(strict_types=1);

namespace App\Services\DateChange;

use App\Enums\BookingStatus;
use App\Enums\DateChangeReason;
use App\Enums\DateChangeStatus;
use App\Models\Booking;
use App\Models\DateChangeRequest;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use App\Support\DateChangeRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Changement de date d'un booking cash accepté ou payé. Le Producteur ou la Face propose, l'autre
 * partie accepte ou refuse. Rien ne change sans accord : refus ou silence laissent la date en place.
 * Les montants ne bougent jamais. Verrou : ligne booking, puis demande.
 */
class BookingDateChangeService
{
    public function __construct(
        private readonly DateChangeNotifier $notifier,
        private readonly AgendaConflictChecker $agenda,
    ) {}

    /** Booking dont la date peut faire l'objet d'une proposition : cash, accepté ou payé, daté. */
    public static function isEligible(Booking $booking): bool
    {
        return $booking->type_contenu !== 'UGC'
            && in_array($booking->status, [BookingStatus::Accepted, BookingStatus::Paid], true)
            && $booking->date_debut !== null;
    }

    public function propose(Booking $booking, User $actor, string $newDate, DateChangeReason $reason): Booking
    {
        /** @var array{request: DateChangeRequest, booking: Booking} $outcome */
        $outcome = DB::transaction(function () use ($booking, $actor, $newDate, $reason): array {
            /** @var Booking $locked */
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            $this->assertParty($locked, $actor);

            if (! self::isEligible($locked)) {
                DateChangeRules::fail('Le changement de date est possible uniquement pour un booking accepté ou payé.');
            }

            /** @var \Carbon\CarbonInterface $debut */
            $debut = $locked->date_debut;

            $used = DateChangeRequest::query()->where('booking_id', $locked->id)->count();
            $hasPending = DateChangeRequest::query()
                ->where('booking_id', $locked->id)
                ->where('status', DateChangeStatus::Pending->value)
                ->exists();

            DateChangeRules::assertCanRequest($debut, $newDate, $used, $hasPending);

            $delta = $this->dayDelta($debut->toDateString(), $newDate);

            $request = DateChangeRequest::query()->create([
                'booking_id' => $locked->id,
                'requested_by_user_id' => $actor->id,
                'requested_by_role' => $actor->id === $locked->producer_id ? 'producer' : 'face',
                'old_date' => $debut->toDateString(),
                'old_end_date' => $locked->date_fin?->toDateString(),
                'new_date' => $newDate,
                'new_end_date' => $locked->date_fin?->copy()->addDays($delta)->toDateString(),
                'reason' => $reason,
                'status' => DateChangeStatus::Pending,
                'respond_by' => DateChangeRules::respondBy($newDate, $debut),
            ]);

            return ['request' => $request, 'booking' => $locked];
        });

        $this->notifyProposed($outcome['booking'], $outcome['request'], $actor);

        return $outcome['booking']->fresh() ?? $outcome['booking'];
    }

    /**
     * L'autre partie répond : `accept` ou `refuse`.
     */
    public function respond(Booking $booking, User $actor, string $decision): Booking
    {
        $stale = false;

        /** @var array{request: DateChangeRequest, booking: Booking}|null $outcome */
        $outcome = DB::transaction(function () use ($booking, $actor, $decision, &$stale): ?array {
            /** @var Booking $locked */
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            $this->assertParty($locked, $actor);

            $request = $this->lockPending($locked);

            if ($request->requested_by_user_id === $actor->id) {
                DateChangeRules::fail('Vous ne pouvez pas répondre à votre propre proposition.');
            }

            // Booking annulé ou expiré entre-temps : la demande tombe, sans notification.
            if (! self::isEligible($locked)) {
                $request->update(['status' => DateChangeStatus::Expired, 'resolved_at' => now()]);
                $stale = true;

                return null;
            }

            if (now()->greaterThanOrEqualTo($request->respond_by)) {
                DateChangeRules::fail('Le délai pour répondre à cette proposition est dépassé.');
            }

            if ($decision === 'refuse') {
                $request->update(['status' => DateChangeStatus::Refused, 'resolved_at' => now()]);

                return ['request' => $request, 'booking' => $locked];
            }

            if ($this->agenda->hasConflict(
                (int) $locked->face_id,
                $request->new_date,
                $request->new_end_date ?? $request->new_date,
                $locked->id,
            )) {
                DateChangeRules::fail($actor->id === $locked->face_id
                    ? 'Vous avez déjà un engagement à cette date : vous ne pouvez pas accepter.'
                    : 'Cette date n\'est pas disponible dans l\'agenda de la Face.');
            }

            $delta = $this->dayDelta($request->old_date->toDateString(), $request->new_date->toDateString());

            // Même décalage en jours pour le début et la fin : l'heure et la durée restent.
            $locked->update([
                'date_debut' => $locked->date_debut?->copy()->addDays($delta),
                'date_fin' => $locked->date_fin?->copy()->addDays($delta),
                'shooting_reminder_sent_at' => null,
                'completion_reminder_sent_at' => null,
            ]);

            $request->update(['status' => DateChangeStatus::Accepted, 'resolved_at' => now()]);

            return ['request' => $request, 'booking' => $locked];
        });

        if ($stale) {
            DateChangeRules::fail('Ce booking n\'est plus modifiable : la proposition a été clôturée.');
        }

        /** @var array{request: DateChangeRequest, booking: Booking} $outcome */
        if ($decision === 'refuse') {
            $this->notifyKept($outcome['booking'], $outcome['request'], silence: false);
        } else {
            $this->notifyAccepted($outcome['booking'], $outcome['request']);
        }

        return $outcome['booking']->fresh() ?? $outcome['booking'];
    }

    /** L'auteur retire sa proposition tant qu'elle est en attente. */
    public function withdraw(Booking $booking, User $actor): Booking
    {
        /** @var DateChangeRequest $request */
        $request = DB::transaction(function () use ($booking, $actor): DateChangeRequest {
            /** @var Booking $locked */
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            $this->assertParty($locked, $actor);

            $pending = $this->lockPending($locked);

            if ($pending->requested_by_user_id !== $actor->id) {
                DateChangeRules::fail('Seul l\'auteur de la proposition peut la retirer.');
            }

            $pending->update(['status' => DateChangeStatus::Withdrawn, 'resolved_at' => now()]);

            return $pending;
        });

        $fresh = $booking->fresh() ?? $booking;
        $this->notifyWithdrawn($fresh, $request, $actor);

        return $fresh;
    }

    // -------------------------------------------------------------------------
    // Cron (date-changes:process)
    // -------------------------------------------------------------------------

    /**
     * Relance unique à mi-parcours de la partie qui doit répondre.
     *
     * @return int nombre de propositions relancées
     */
    public function sendReminders(): int
    {
        $sent = 0;

        $requests = DateChangeRequest::query()
            ->whereNotNull('booking_id')
            ->where('status', DateChangeStatus::Pending->value)
            ->whereNull('reminder_sent_at')
            ->where('respond_by', '>', now())
            ->get()
            ->filter(function (DateChangeRequest $request): bool {
                $halfway = $request->created_at->copy()->addSeconds((int) floor($request->created_at->diffInSeconds($request->respond_by) / 2));

                return now()->greaterThanOrEqualTo($halfway);
            });

        foreach ($requests as $request) {
            try {
                $claimed = DateChangeRequest::query()
                    ->whereKey($request->id)
                    ->where('status', DateChangeStatus::Pending->value)
                    ->whereNull('reminder_sent_at')
                    ->update(['reminder_sent_at' => now()]);

                if ($claimed === 0) {
                    continue;
                }

                /** @var Booking|null $booking */
                $booking = Booking::query()->find($request->booking_id);

                if ($booking !== null && self::isEligible($booking)) {
                    $this->notifyReminder($booking, $request);
                    $sent++;
                }
            } catch (\Throwable $e) {
                Log::critical('Relance de changement de date de booking échouée', [
                    'date_change_request_id' => $request->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * Expire les propositions dont l'échéance est passée : la date reste, les deux parties sont prévenues.
     *
     * @return array{processed: int, failures: int}
     */
    public function expireDue(): array
    {
        $processed = 0;
        $failures = 0;

        $ids = DateChangeRequest::query()
            ->whereNotNull('booking_id')
            ->where('status', DateChangeStatus::Pending->value)
            ->where('respond_by', '<=', now())
            ->pluck('id');

        foreach ($ids as $id) {
            try {
                $this->expireOne((int) $id);
                $processed++;
            } catch (\Throwable $e) {
                $failures++;
                Log::critical('Expiration de changement de date de booking échouée', [
                    'date_change_request_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['processed' => $processed, 'failures' => $failures];
    }

    private function expireOne(int $requestId): void
    {
        /** @var array{request: DateChangeRequest, booking: Booking, notify: bool}|null $outcome */
        $outcome = DB::transaction(function () use ($requestId): ?array {
            /** @var DateChangeRequest|null $peek */
            $peek = DateChangeRequest::query()->find($requestId);

            if ($peek === null || $peek->booking_id === null) {
                return null;
            }

            // Même ordre de verrous que les réponses : booking, puis demande.
            /** @var Booking|null $booking */
            $booking = Booking::query()->lockForUpdate()->find($peek->booking_id);
            /** @var DateChangeRequest|null $request */
            $request = DateChangeRequest::query()->lockForUpdate()->find($requestId);

            if ($booking === null || $request === null || ! $request->isPending() || $request->respond_by->greaterThan(now())) {
                return null;
            }

            $request->update(['status' => DateChangeStatus::Expired, 'resolved_at' => now()]);

            return ['request' => $request, 'booking' => $booking, 'notify' => self::isEligible($booking)];
        });

        if ($outcome !== null && $outcome['notify']) {
            $this->notifyKept($outcome['booking'], $outcome['request'], silence: true);
        }
    }

    // -------------------------------------------------------------------------
    // Utilitaires
    // -------------------------------------------------------------------------

    private function assertParty(Booking $booking, User $actor): void
    {
        if ($actor->id !== $booking->producer_id && $actor->id !== $booking->face_id) {
            DateChangeRules::fail('Vous ne faites pas partie de ce booking.');
        }
    }

    /** Dernière demande en attente du booking, verrouillée (le booking l'est déjà). */
    private function lockPending(Booking $booking): DateChangeRequest
    {
        /** @var DateChangeRequest|null $request */
        $request = DateChangeRequest::query()
            ->where('booking_id', $booking->id)
            ->where('status', DateChangeStatus::Pending->value)
            ->latest('id')
            ->lockForUpdate()
            ->first();

        if ($request === null) {
            DateChangeRules::fail('Aucune proposition de changement de date n\'est en cours.');
        }

        return $request;
    }

    /** Écart signé en jours entre deux dates `Y-m-d`. */
    private function dayDelta(string $oldDate, string $newDate): int
    {
        return (int) round(Carbon::parse($oldDate)->startOfDay()->diffInDays(Carbon::parse(substr($newDate, 0, 10))->startOfDay(), false));
    }

    private function otherPartyId(Booking $booking, int $userId): int
    {
        return $userId === (int) $booking->producer_id ? (int) $booking->face_id : (int) $booking->producer_id;
    }

    private function bookingUrl(Booking $booking, int $recipientUserId): string
    {
        return $recipientUserId === (int) $booking->producer_id
            ? "/producer/bookings/{$booking->uuid}"
            : "/face/bookings/{$booking->uuid}";
    }

    private function nameOf(int $userId): string
    {
        $user = User::query()->with('userable')->find($userId);
        $profile = $user?->userable;
        $name = $profile instanceof Face || $profile instanceof Producer ? trim($profile->display_name) : '';

        return $name !== '' ? $name : 'L\'autre partie';
    }

    // -------------------------------------------------------------------------
    // Notifications (toujours après commit)
    // -------------------------------------------------------------------------

    private function notifyProposed(Booking $booking, DateChangeRequest $request, User $author): void
    {
        $recipientId = $this->otherPartyId($booking, $author->id);
        $date = DateChangeRules::formatDay($request->new_date);
        $limit = DateChangeRules::formatDeadline($request->respond_by);
        $message = $this->nameOf($author->id)." propose de déplacer votre tournage au {$date}. Répondez avant le {$limit}.";

        $this->notifier->notifySafely(
            userId: $recipientId,
            type: 'booking_date_change_proposed',
            data: [
                'message' => $message,
                'booking_id' => $booking->id,
                'respond_by' => $request->respond_by->toIso8601String(),
                'url' => $this->bookingUrl($booking, $recipientId),
            ],
            mail: [
                'subject' => "Proposition de nouvelle date pour votre tournage : {$date}",
                'heading' => 'Proposition de nouvelle date',
                'lines' => [$message, 'Sans réponse de votre part, la date du tournage reste inchangée.'],
                'cta_label' => 'Répondre',
            ],
        );
    }

    private function notifyWithdrawn(Booking $booking, DateChangeRequest $request, User $author): void
    {
        $recipientId = $this->otherPartyId($booking, $author->id);
        $oldDate = DateChangeRules::formatDay($request->old_date);
        $message = $this->nameOf($author->id)." a retiré sa proposition : le tournage reste le {$oldDate}.";

        $this->notifier->notifySafely(
            userId: $recipientId,
            type: 'booking_date_change_withdrawn',
            data: [
                'message' => $message,
                'booking_id' => $booking->id,
                'url' => $this->bookingUrl($booking, $recipientId),
            ],
            mail: [
                'subject' => "Proposition de nouvelle date retirée : le tournage reste le {$oldDate}",
                'heading' => 'Proposition retirée',
                'lines' => [$message],
                'cta_label' => 'Voir le booking',
            ],
        );
    }

    private function notifyAccepted(Booking $booking, DateChangeRequest $request): void
    {
        $authorId = (int) $request->requested_by_user_id;
        $date = DateChangeRules::formatDay($request->new_date);
        $message = $this->nameOf($this->otherPartyId($booking, $authorId))." a accepté la nouvelle date : le tournage a lieu le {$date}.";

        $this->notifier->notifySafely(
            userId: $authorId,
            type: 'booking_date_change_accepted',
            data: [
                'message' => $message,
                'booking_id' => $booking->id,
                'url' => $this->bookingUrl($booking, $authorId),
            ],
            mail: [
                'subject' => "Nouvelle date acceptée : {$date}",
                'heading' => 'Nouvelle date acceptée',
                'lines' => [$message],
                'cta_label' => 'Voir le booking',
            ],
        );
    }

    /** La date reste : refus (auteur seul prévenu) ou silence (les deux parties). */
    private function notifyKept(Booking $booking, DateChangeRequest $request, bool $silence): void
    {
        $authorId = (int) $request->requested_by_user_id;
        $otherId = $this->otherPartyId($booking, $authorId);
        $oldDate = DateChangeRules::formatDay($request->old_date);

        $authorMessage = $silence
            ? "Sans réponse, la proposition de nouvelle date a expiré : le tournage reste le {$oldDate}."
            : $this->nameOf($otherId)." a refusé la nouvelle date : le tournage reste le {$oldDate}.";

        $recipients = [$authorId => $authorMessage];

        if ($silence) {
            $recipients[$otherId] = "Sans réponse de votre part, la proposition de nouvelle date a expiré : le tournage reste le {$oldDate}.";
        }

        foreach ($recipients as $userId => $message) {
            $this->notifier->notifySafely(
                userId: $userId,
                type: 'booking_date_change_kept',
                data: [
                    'message' => $message,
                    'booking_id' => $booking->id,
                    'url' => $this->bookingUrl($booking, $userId),
                ],
                mail: [
                    'subject' => "Le tournage reste le {$oldDate}",
                    'heading' => 'La date du tournage ne change pas',
                    'lines' => [$message],
                    'cta_label' => 'Voir le booking',
                ],
            );
        }
    }

    private function notifyReminder(Booking $booking, DateChangeRequest $request): void
    {
        $recipientId = $this->otherPartyId($booking, (int) $request->requested_by_user_id);
        $hours = DateChangeRules::hoursLeft($request->respond_by);
        $oldDate = DateChangeRules::formatDay($request->old_date);
        $message = "Plus que {$hours} h pour répondre à la nouvelle date de votre tournage du {$oldDate}.";

        $this->notifier->notifySafely(
            userId: $recipientId,
            type: 'date_change_reminder',
            data: [
                'message' => $message,
                'booking_id' => $booking->id,
                'respond_by' => $request->respond_by->toIso8601String(),
                'url' => $this->bookingUrl($booking, $recipientId),
            ],
            mail: [
                'subject' => "Plus que {$hours} h pour répondre à la nouvelle date",
                'heading' => 'Réponse attendue',
                'lines' => [$message],
                'cta_label' => 'Répondre',
            ],
        );
    }
}
