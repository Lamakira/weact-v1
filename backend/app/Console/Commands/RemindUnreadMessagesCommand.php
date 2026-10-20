<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Resources\BookingThreadListResource;
use App\Mail\UnreadMessagesMail;
use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\Conversation;
use App\Models\Face;
use App\Models\Message;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * E-mail « message non lu » : un message resté non lu plus d'1 heure déclenche au plus UN
 * e-mail par fil (conversation de mission ou chat de booking) et par destinataire sur 24 h.
 *
 * Le dernier envoi est mémorisé dans `message_unread_email_logs` (une ligne par destinataire
 * et par fil) ; la ligne sert aussi de verrou atomique entre deux exécutions concurrentes.
 */
class RemindUnreadMessagesCommand extends Command
{
    /** Ancienneté minimale d'un message non lu pour déclencher l'e-mail. */
    private const UNREAD_AFTER_HOURS = 1;

    /** Délai minimal entre deux e-mails pour un même fil et un même destinataire. */
    private const COOLDOWN_HOURS = 24;

    private const TYPE_CONVERSATION = 'conversation';

    private const TYPE_BOOKING = 'booking';

    protected $signature = 'messages:remind-unread';

    protected $description = 'Send an email to users whose messages stayed unread for more than 1 hour (max 1 email per thread and recipient per 24 hours)';

    private int $sent = 0;

    private int $failed = 0;

    public function handle(): int
    {
        $threshold = now()->subHours(self::UNREAD_AFTER_HOURS);

        Message::query()
            ->whereNull('read_at')
            ->where('created_at', '<=', $threshold)
            ->distinct()
            ->orderBy('conversation_id')
            ->pluck('conversation_id')
            ->chunk(100)
            ->each(function ($ids) use ($threshold): void {
                $conversations = Conversation::query()
                    ->whereIn('id', $ids)
                    ->with(['candidature.face.user', 'candidature.mission.producer.user'])
                    ->get();

                foreach ($conversations as $conversation) {
                    $this->remindConversation($conversation, $threshold);
                }
            });

        BookingMessage::query()
            ->whereNull('read_at')
            ->where('created_at', '<=', $threshold)
            ->distinct()
            ->orderBy('booking_id')
            ->pluck('booking_id')
            ->chunk(100)
            ->each(function ($ids) use ($threshold): void {
                $bookings = Booking::query()
                    ->whereIn('id', $ids)
                    ->with(['face.userable', 'producer.userable'])
                    ->get();

                foreach ($bookings as $booking) {
                    $this->remindBooking($booking, $threshold);
                }
            });

        $this->info("Done. Sent: {$this->sent}, Failed: {$this->failed}.");

        return self::SUCCESS;
    }

    private function remindConversation(Conversation $conversation, \DateTimeInterface $threshold): void
    {
        $candidature = $conversation->candidature;
        $faceUser = $candidature?->face?->user;
        $producerUser = $candidature?->mission?->producer?->user;

        foreach ([[$faceUser, $producerUser], [$producerUser, $faceUser]] as [$recipient, $sender]) {
            if ($recipient === null || $sender === null) {
                continue;
            }

            // Accès au fil (ConversationPolicy::view) et e-mail vérifié (le mur de vérification
            // empêche de toute façon de lire la messagerie avant).
            if (! $recipient->hasVerifiedEmail() || ! Gate::forUser($recipient)->allows('view', $conversation)) {
                continue;
            }

            $unread = Message::query()
                ->where('conversation_id', $conversation->id)
                ->whereNull('read_at')
                ->where('sender_id', '!=', $recipient->id)
                ->pluck('created_at');

            if (! $unread->contains(fn ($createdAt) => $createdAt <= $threshold)) {
                continue;
            }

            $this->remind(
                $recipient,
                $sender,
                self::TYPE_CONVERSATION,
                $conversation->id,
                $unread->count(),
                (string) $candidature->mission?->titre,
                $conversation->uuid,
            );
        }
    }

    private function remindBooking(Booking $booking, \DateTimeInterface $threshold): void
    {
        foreach ([[$booking->face, $booking->producer], [$booking->producer, $booking->face]] as [$recipient, $sender]) {
            if ($recipient === null || $sender === null) {
                continue;
            }

            // Accès au chat selon BookingPolicy::viewMessages (statut + partie du booking)
            if (! $recipient->hasVerifiedEmail() || ! Gate::forUser($recipient)->allows('viewMessages', $booking)) {
                continue;
            }

            $unread = BookingMessage::query()
                ->where('booking_id', $booking->id)
                ->whereNull('read_at')
                ->where('sender_id', '!=', $recipient->id)
                ->pluck('created_at');

            if (! $unread->contains(fn ($createdAt) => $createdAt <= $threshold)) {
                continue;
            }

            $this->remind(
                $recipient,
                $sender,
                self::TYPE_BOOKING,
                $booking->id,
                $unread->count(),
                BookingThreadListResource::title($booking),
                $booking->uuid,
            );
        }
    }

    private function remind(User $recipient, User $sender, string $type, int $threadId, int $unreadCount, string $contextLabel, string $threadUuid): void
    {
        $email = trim((string) $recipient->email);
        if ($email === '') {
            return;
        }

        $claim = $this->claim($recipient->id, $type, $threadId);
        if ($claim === null) {
            return; // déjà relancé dans les dernières 24 h
        }

        try {
            $role = $recipient->userable_type === Face::class ? 'face' : 'producer';
            $senderName = data_get($sender, 'userable.display_name');

            Mail::to($email)->queue(new UnreadMessagesMail(
                is_string($senderName) && $senderName !== '' ? $senderName : 'Votre interlocuteur',
                $unreadCount,
                $contextLabel,
                rtrim((string) config('app.frontend_url'), '/')."/{$role}/conversations/{$threadUuid}",
            ));

            $this->sent++;
        } catch (Throwable $e) {
            // Non bloquant : l'envoi est libéré pour que le prochain passage réessaie
            $this->release($recipient->id, $type, $threadId, $claim);
            Log::warning('Unread messages email failed', [
                'user_id' => $recipient->id,
                'thread_type' => $type,
                'thread_id' => $threadId,
                'error' => $e->getMessage(),
            ]);
            $this->failed++;
        }
    }

    /**
     * Réserve l'envoi de façon atomique : insertion de la ligne, ou mise à jour si le dernier
     * envoi date de plus de 24 h. Retourne l'état à restaurer en cas d'échec d'envoi
     * (['at' => horodatage réservé, 'previous' => ancien horodatage ou null), null si refusé.
     *
     * @return array{at: \Illuminate\Support\Carbon, previous: string|null}|null
     */
    private function claim(int $userId, string $type, int $threadId): ?array
    {
        $now = now()->startOfSecond();
        $keys = ['user_id' => $userId, 'thread_type' => $type, 'thread_id' => $threadId];
        $table = DB::table('message_unread_email_logs');

        $inserted = $table->insertOrIgnore($keys + ['sent_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        if ($inserted === 1) {
            return ['at' => $now, 'previous' => null];
        }

        $previous = DB::table('message_unread_email_logs')->where($keys)->value('sent_at');
        $claimed = DB::table('message_unread_email_logs')
            ->where($keys)
            ->where('sent_at', '<=', now()->subHours(self::COOLDOWN_HOURS))
            ->update(['sent_at' => $now, 'updated_at' => $now]);

        return $claimed === 1 ? ['at' => $now, 'previous' => $previous === null ? null : (string) $previous] : null;
    }

    /**
     * @param  array{at: \Illuminate\Support\Carbon, previous: string|null}  $claim
     */
    private function release(int $userId, string $type, int $threadId, array $claim): void
    {
        $query = DB::table('message_unread_email_logs')
            ->where(['user_id' => $userId, 'thread_type' => $type, 'thread_id' => $threadId])
            ->where('sent_at', $claim['at']);

        if ($claim['previous'] === null) {
            $query->delete();
        } else {
            $query->update(['sent_at' => $claim['previous']]);
        }
    }
}
