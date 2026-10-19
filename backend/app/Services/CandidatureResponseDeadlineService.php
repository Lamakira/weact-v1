<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionStatus;
use App\Mail\CandidatureResponseReminderMail;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Lot B6 (décision D3) : réponse du Producer aux candidatures sous 72 h après leur clôture
 * (Mission::producerResponseDeadline). Relance unique à 24 h de l'échéance, puis expiration
 * des candidatures restées en attente. Une candidature d'une sélection en cours de paiement
 * (mission pending_payment, ou entry de paiement Pending) n'est jamais concernée.
 */
class CandidatureResponseDeadlineService
{
    /** Échéance dépassée depuis plus que cela : expiration sans notification (historique). */
    public const SILENT_EXPIRY_AFTER_HOURS = 24;

    /**
     * Candidatures en attente d'une réponse du Producer, hors sélection en cours de paiement.
     *
     * @return Builder<Candidature>
     */
    public function unansweredCandidatures(Mission $mission): Builder
    {
        return Candidature::query()
            ->where('mission_id', $mission->id)
            ->where('status', CandidatureStatus::Pending->value)
            ->whereDoesntHave('paymentEntry', fn ($query) => $query->where('escrow_status', EscrowStatus::Pending->value));
    }

    /**
     * Expire les candidatures sans réponse d'une mission dont l'échéance est atteinte.
     * Verrou mission puis candidatures (même ordre que la préparation d'une sélection) ;
     * Faces prévenues après le commit. Retourne le nombre de candidatures expirées.
     */
    public function expireUnanswered(Mission $mission): int
    {
        /** @var list<int> $expiredIds */
        $expiredIds = DB::transaction(function () use ($mission): array {
            /** @var Mission|null $locked */
            $locked = Mission::query()->lockForUpdate()->find($mission->id);

            if ($locked === null || $locked->status === MissionStatus::PendingPayment) {
                return [];
            }

            $deadline = $locked->producerResponseDeadline();

            if ($deadline === null || now()->lt($deadline)) {
                return [];
            }

            $ids = $this->unansweredCandidatures($locked)
                ->lockForUpdate()
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            if ($ids === []) {
                return [];
            }

            Candidature::query()
                ->whereIn('id', $ids)
                ->where('status', CandidatureStatus::Pending->value)
                ->update(['status' => CandidatureStatus::Expired->value]);

            return $ids;
        }, 3);

        // Historique (échéance dépassée depuis plus de 24 h, ex. au premier passage après le
        // déploiement) : expirée sans notification, pour ne pas envoyer des centaines d'avis
        // sur des candidatures anciennes. Le cron horaire notifie toutes les nouvelles.
        $deadline = $mission->producerResponseDeadline();
        if ($deadline !== null && $deadline->lt(now()->subHours(self::SILENT_EXPIRY_AFTER_HOURS))) {
            return count($expiredIds);
        }

        foreach ($expiredIds as $candidatureId) {
            $this->notifyFaceOfExpiry($mission, $candidatureId);
        }

        return count($expiredIds);
    }

    /**
     * Relance unique du Producer (in-app + e-mail) quand il reste 24 h ou moins avant
     * l'échéance et que des candidatures attendent encore sa réponse.
     */
    public function remindProducer(Mission $mission): bool
    {
        $deadline = $mission->producerResponseDeadline();

        if ($deadline === null
            || $mission->status === MissionStatus::PendingPayment
            || now()->gte($deadline)
            || now()->lt($deadline->copy()->subHours(Mission::PRODUCER_RESPONSE_REMINDER_BEFORE_HOURS))) {
            return false;
        }

        $pendingCount = $this->unansweredCandidatures($mission)->count();

        if ($pendingCount === 0) {
            return false;
        }

        // Réservation atomique : un passage concurrent ne renvoie rien.
        $claimed = Mission::query()
            ->whereKey($mission->id)
            ->whereNull('candidature_response_reminder_sent_at')
            ->update(['candidature_response_reminder_sent_at' => now()]);

        if ($claimed !== 1) {
            return false;
        }

        $producerUser = $mission->producer?->user;

        if (! $producerUser instanceof User) {
            return false;
        }

        $deadlineLabel = (string) $mission->producerResponseDeadlineLabel();

        try {
            Notification::create([
                'user_id' => $producerUser->id,
                'type' => 'mission_candidatures_response_reminder',
                'data' => [
                    'message' => "Rappel : {$pendingCount} candidature(s) attendent votre réponse pour la mission « {$mission->titre} ». Répondez avant le {$deadlineLabel}, sinon elles expireront.",
                    'mission_id' => $mission->id,
                    'url' => "/producer/missions/{$mission->uuid}/candidatures",
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Candidature response reminder notification failed', [
                'mission_id' => $mission->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $email = trim((string) $producerUser->email);
            if ($email !== '') {
                Mail::to($email)->queue(new CandidatureResponseReminderMail($mission, $pendingCount, $deadlineLabel));
            }
        } catch (\Throwable $e) {
            Log::warning('Candidature response reminder email failed', [
                'mission_id' => $mission->id,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }

    private function notifyFaceOfExpiry(Mission $mission, int $candidatureId): void
    {
        try {
            $faceId = Candidature::query()->whereKey($candidatureId)->value('face_id');
            $faceUserId = User::query()
                ->where('userable_type', Face::class)
                ->where('userable_id', $faceId)
                ->value('id');

            if ($faceUserId === null) {
                return;
            }

            Notification::create([
                'user_id' => $faceUserId,
                'type' => 'candidature_expired_no_response',
                'data' => [
                    'message' => "Le producteur n'a pas répondu à votre candidature pour la mission « {$mission->titre} » dans le délai prévu : elle a expiré.",
                    'mission_id' => $mission->id,
                    'url' => "/face/missions/{$mission->uuid}",
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Candidature expiry notification failed', [
                'candidature_id' => $candidatureId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
