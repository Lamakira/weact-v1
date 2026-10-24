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
use App\Services\Messaging\FaceResponsiveness;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * E-mail « message non lu » : un message resté non lu plus d'1 heure (15 minutes quand le tournage
 * du fil est proche, cf. FaceResponsiveness) déclenche au plus UN e-mail par fil (conversation de
 * mission ou chat de booking) et par destinataire sur 24 h. Vaut dans les deux sens (Face et Producteur).
 *
 * Le dernier envoi est mémorisé dans `message_unread_email_logs` (une ligne par destinataire
 * et par fil) ; la ligne sert aussi de verrou atomique entre deux exécutions concurrentes.
 */
class RemindUnreadMessagesCommand extends Command
{
    /** Délai minimal entre deux e-mails pour un même fil et un même destinataire. */
    private const COOLDOWN_HOURS = 24;

    /**
     * Historique pris en compte : seuls les messages non lus créés depuis moins de 72 h comptent.
     * Évite une vague d'e-mails au déploiement (ou sur de vieux fils jamais ouverts) : un message
     * non lu plus ancien ne déclenche plus rien.
     */
    private const HISTORY_HOURS = 72;

    private const TYPE_CONVERSATION = 'conversation';

    private const TYPE_BOOKING = 'booking';

    protected $signature = 'messages:remind-unread';

    protected $description = 'Send an email to users whose messages stayed unread for more than 1 hour, or 15 minutes when the shoot is near (max 1 email per thread and recipient per 24 hours)';

    private int $sent = 0;

    private int $failed = 0;

    private FaceResponsiveness $responsiveness;

    public function handle(FaceResponsiveness $responsiveness): int
    {
        $this->responsiveness = $responsiveness;
        // Seuil le plus court : chaque fil applique ensuite son propre seuil (tournage proche ou non)
        $threshold = now()->subMinutes(FaceResponsiveness::URGENT_UNREAD_EMAIL_AFTER_MINUTES);
        $oldest = now()->subHours(self::HISTORY_HOURS);

        Message::query()
            ->whereNull('read_at')
            ->whereBetween('created_at', [$oldest, $threshold])
            ->distinct()
            ->orderBy('conversation_id')
            ->pluck('conversation_id')
            ->chunk(100)
            ->each(function ($ids) use ($oldest): void {
                $conversations = Conversation::query()
                    ->whereIn('id', $ids)
                    ->with(['candidature.face.user', 'candidature.mission.producer.user'])
                    ->get();

                foreach ($conversations as $conversation) {
                    $this->remindConversation($conversation, $oldest);
                }
            });

        BookingMessage::query()
            ->whereNull('read_at')
            ->whereBetween('created_at', [$oldest, $threshold])
            ->distinct()
            ->orderBy('booking_id')
            ->pluck('booking_id')
            ->chunk(100)
            ->each(function ($ids) use ($oldest): void {
                $bookings = Booking::query()
                    ->whereIn('id', $ids)
                    ->with(['face.userable', 'producer.userable'])
                    ->get();

                foreach ($bookings as $booking) {
                    $this->remindBooking($booking, $oldest);
                }
            });

        $this->info("Done. Sent: {$this->sent}, Failed: {$this->failed}.");

        return self::SUCCESS;
    }

    private function remindConversation(Conversation $conversation, \DateTimeInterface $oldest): void
    {
        $candidature = $conversation->candidature;
        $threshold = $this->threadThreshold($candidature?->mission?->shootingDayStartsAt());
        $faceUser = $candidature?->face?->user;
        $producerUser = $candidature?->mission?->producer?->user;
        // Noms lus sur les profils déjà chargés : aucune requête par destinataire
        $faceName = $candidature?->face?->display_name;
        $producerName = $candidature?->mission?->producer?->display_name;

        foreach ([[$faceUser, $producerUser, $producerName], [$producerUser, $faceUser, $faceName]] as [$recipient, $sender, $senderName]) {
            if ($recipient === null || $sender === null) {
                continue;
            }

            // Compte actif, accès au fil (ConversationPolicy::view) et e-mail vérifié (le mur de vérification
            // empêche de toute façon de lire la messagerie avant).
            if (! $recipient->is_active || ! $recipient->hasVerifiedEmail() || ! Gate::forUser($recipient)->allows('view', $conversation)) {
                continue;
            }

            $unread = Message::query()
                ->where('conversation_id', $conversation->id)
                ->whereNull('read_at')
                ->where('sender_id', '!=', $recipient->id)
                ->where('created_at', '>=', $oldest)
                ->pluck('created_at');

            $this->remind(
                $recipient,
                $senderName,
                self::TYPE_CONVERSATION,
                $conversation->id,
                $unread,
                $threshold,
                (string) $candidature->mission?->titre,
                $conversation->uuid,
            );
        }
    }

    private function remindBooking(Booking $booking, \DateTimeInterface $oldest): void
    {
        $threshold = $this->threadThreshold($booking->shootingDayStartsAt());

        foreach ([[$booking->face, $booking->producer], [$booking->producer, $booking->face]] as [$recipient, $sender]) {
            if ($recipient === null || $sender === null) {
                continue;
            }

            $senderName = data_get($sender, 'userable.display_name');

            // Accès au chat selon BookingPolicy::viewMessages (statut + partie du booking)
            if (! $recipient->is_active || ! $recipient->hasVerifiedEmail() || ! Gate::forUser($recipient)->allows('viewMessages', $booking)) {
                continue;
            }

            $unread = BookingMessage::query()
                ->where('booking_id', $booking->id)
                ->whereNull('read_at')
                ->where('sender_id', '!=', $recipient->id)
                ->where('created_at', '>=', $oldest)
                ->pluck('created_at');

            $this->remind(
                $recipient,
                $senderName,
                self::TYPE_BOOKING,
                $booking->id,
                $unread,
                $threshold,
                BookingThreadListResource::title($booking),
                $booking->uuid,
            );
        }
    }

    /** Seuil propre au fil : maintenant moins le délai « non lu » (15 min si tournage proche, 60 min sinon). */
    private function threadThreshold(?\Carbon\CarbonInterface $shootStart): \Illuminate\Support\Carbon
    {
        return now()->subMinutes($this->responsiveness->unreadEmailAfterMinutes($shootStart));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Support\Carbon>  $unread  dates des non lus de l'autre partie (fenêtre de 72 h)
     */
    private function remind(User $recipient, mixed $senderName, string $type, int $threadId, \Illuminate\Support\Collection $unread, \DateTimeInterface $threshold, string $contextLabel, string $threadUuid): void
    {
        $email = trim((string) $recipient->email);
        if ($email === '') {
            return;
        }

        // Un e-mail n'est dû que pour un message non lu, assez ancien, ET postérieur au dernier
        // e-mail de ce fil : sans nouveau message, un fil jamais ouvert ne relance pas.
        $lastSent = $this->lastSentAt($recipient->id, $type, $threadId);
        $qualifying = $unread->filter(fn ($createdAt) => $createdAt <= $threshold && ($lastSent === null || $createdAt > $lastSent));
        if ($qualifying->isEmpty()) {
            return;
        }

        $unreadCount = $unread->count();
        $claim = $this->claim($recipient->id, $type, $threadId);
        if ($claim === null) {
            return; // déjà relancé dans les dernières 24 h
        }

        try {
            $role = $recipient->userable_type === Face::class ? 'face' : 'producer';

            Mail::to($email)->queue(new UnreadMessagesMail(
                is_string($senderName) && $senderName !== '' ? $senderName : 'Votre interlocuteur',
                $unreadCount,
                $contextLabel,
                rtrim((string) config('app.frontend_url'), '/')."/{$role}/conversations/{$threadUuid}".($type === self::TYPE_BOOKING ? '?kind=booking' : ''),
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

    private function lastSentAt(int $userId, string $type, int $threadId): ?\Illuminate\Support\Carbon
    {
        $value = DB::table('message_unread_email_logs')
            ->where(['user_id' => $userId, 'thread_type' => $type, 'thread_id' => $threadId])
            ->value('sent_at');

        return $value === null ? null : \Illuminate\Support\Carbon::parse($value);
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
