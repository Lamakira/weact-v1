<?php

declare(strict_types=1);

namespace App\Services\DateChange;

use App\Mail\DateChangeMail;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use App\Support\DateChangeRules;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Notifications in-app et e-mails du changement de date. Jamais bloquant : une panne est
 * journalisée, jamais propagée. À appeler HORS transaction (après commit).
 */
class DateChangeNotifier
{
    /**
     * Crée la notification in-app puis met l'e-mail en file (si `$mail` est fourni).
     *
     * @param  array<string, mixed>  $data  doit contenir `message` et `url`
     * @param  array{subject: string, heading: string, lines: list<string>, cta_label: string}|null  $mail
     */
    public function notifySafely(?int $userId, string $type, array $data, ?array $mail = null): void
    {
        if ($userId === null) {
            return;
        }

        try {
            Notification::query()->create([
                'user_id' => $userId,
                'type' => $type,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            Log::warning('DateChangeNotifier: notification failed', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }

        if ($mail === null) {
            return;
        }

        try {
            $email = trim((string) User::query()->whereKey($userId)->value('email'));

            if ($email !== '') {
                Mail::to($email)->queue(new DateChangeMail(
                    $mail['subject'],
                    $mail['heading'],
                    $mail['lines'],
                    $mail['cta_label'],
                    rtrim((string) config('app.frontend_url'), '/').(string) ($data['url'] ?? ''),
                ));
            }
        } catch (\Throwable $e) {
            Log::warning('DateChangeNotifier: mail queue failed', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function userIdForFace(int $faceId): ?int
    {
        /** @var User|null $user */
        $user = User::query()
            ->where('userable_type', Face::class)
            ->where('userable_id', $faceId)
            ->first();

        return $user?->id;
    }

    public function userIdForProducer(int $producerId): ?int
    {
        /** @var User|null $user */
        $user = User::query()
            ->where('userable_type', Producer::class)
            ->where('userable_id', $producerId)
            ->first();

        return $user?->id;
    }

    /**
     * La date de tournage a changé : informe les candidatures données, sans réponse attendue.
     * Mission publiée (Pending et Accepted) comme mission payée (Accepted).
     *
     * @param  iterable<Candidature>  $candidatures
     */
    public function notifyMissionDateChanged(Mission $mission, iterable $candidatures): void
    {
        $date = DateChangeRules::formatDay((string) $mission->date_tournage?->toDateString());
        $message = "Le tournage de « {$mission->titre} » passe au {$date}.";

        foreach ($candidatures as $candidature) {
            $this->notifySafely(
                userId: $this->userIdForFace((int) $candidature->face_id),
                type: 'mission_date_changed',
                data: [
                    'message' => $message,
                    'mission_id' => $mission->id,
                    'candidature_id' => $candidature->id,
                    'url' => "/face/missions/{$mission->uuid}",
                ],
                mail: [
                    'subject' => "La date de tournage a changé : {$date}",
                    'heading' => 'La date de tournage a changé',
                    'lines' => [$message],
                    'cta_label' => 'Voir la mission',
                ],
            );
        }
    }
}
