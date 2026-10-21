<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DisputeResolutionOutcome;
use App\Enums\EscrowStatus;
use App\Mail\MissionAttendanceDisputedMail;
use App\Mail\MissionAttendanceDisputeResolvedMail;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPaymentCandidature;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Lot D5 : notifications (in-app + e-mail) de la contestation d'une absence en mission.
 * Notifications seulement : aucun règlement ici. À appeler APRÈS le commit de la transaction
 * de règlement ; chaque envoi est isolé et jamais bloquant (échec = warning).
 */
class MissionAttendanceDisputeNotifier
{
    /** La Face vient de contester son absence : prévient le Producteur. */
    public function disputeOpened(MissionPaymentCandidature $entry): void
    {
        try {
            [$mission, $face, $producerUser] = $this->parties($entry);

            if ($mission === null || $producerUser === null) {
                return;
            }

            $faceName = $this->faceName($face);

            $this->notifySafely(
                user: $producerUser,
                type: 'mission_attendance_disputed',
                message: "{$faceName} conteste son absence à la mission « {$mission->titre} ». Les fonds restent bloqués le temps que l'équipe WEACT tranche.",
                mission: $mission,
                space: 'producer',
                entry: $entry,
            );

            $this->mail($producerUser, new MissionAttendanceDisputedMail($mission, $faceName, (int) $entry->montant_face_recoit));
        } catch (\Throwable $e) {
            Log::warning('MissionAttendanceDisputeNotifier::disputeOpened failed', [
                'entry_id' => $entry->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Le litige est tranché : prévient les deux parties de l'issue. Ne notifie que si le règlement
     * a effectivement eu lieu (entry versée / remboursée).
     */
    public function disputeResolved(MissionPaymentCandidature $entry, DisputeResolutionOutcome $outcome): void
    {
        try {
            $entry->refresh();
            $expected = $outcome === DisputeResolutionOutcome::FavorFace ? EscrowStatus::Released : EscrowStatus::Refunded;

            if ($entry->escrow_status !== $expected) {
                return;
            }

            [$mission, $face, $producerUser, $faceUser] = $this->parties($entry);

            if ($mission === null) {
                return;
            }

            $amount = (int) $entry->montant_face_recoit;
            $favorFace = $outcome === DisputeResolutionOutcome::FavorFace;

            if ($faceUser !== null) {
                $this->notifySafely(
                    user: $faceUser,
                    type: 'mission_attendance_dispute_resolved',
                    message: $favorFace
                        ? "Litige tranché en votre faveur pour la mission « {$mission->titre} » : votre présence est reconnue et {$amount} XOF ont été crédités sur votre portefeuille."
                        : "Litige tranché pour la mission « {$mission->titre} » : l'absence est maintenue, la place est remboursée au Producteur.",
                    mission: $mission,
                    space: 'face',
                    entry: $entry,
                    extra: ['outcome' => $outcome->value, 'amount' => $amount],
                );

                $this->mail($faceUser, new MissionAttendanceDisputeResolvedMail(
                    $mission,
                    MissionAttendanceDisputeResolvedMail::RECIPIENT_FACE,
                    $outcome,
                    $face instanceof Face ? trim((string) $face->prenom) : '',
                    $amount,
                ));
            }

            if ($producerUser !== null) {
                $this->notifySafely(
                    user: $producerUser,
                    type: 'mission_attendance_dispute_resolved',
                    message: $favorFace
                        ? "Litige tranché en faveur de la Face pour la mission « {$mission->titre} » : sa présence est reconnue, la place n'est pas remboursée."
                        : "Litige tranché en votre faveur pour la mission « {$mission->titre} » : {$amount} XOF ont été crédités sur votre portefeuille.",
                    mission: $mission,
                    space: 'producer',
                    entry: $entry,
                    extra: ['outcome' => $outcome->value, 'amount' => $amount],
                );

                $this->mail($producerUser, new MissionAttendanceDisputeResolvedMail(
                    $mission,
                    MissionAttendanceDisputeResolvedMail::RECIPIENT_PRODUCER,
                    $outcome,
                    '',
                    $amount,
                ));
            }
        } catch (\Throwable $e) {
            Log::warning('MissionAttendanceDisputeNotifier::disputeResolved failed', [
                'entry_id' => $entry->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{0: ?Mission, 1: ?Face, 2: ?User, 3: ?User} mission, Face, user Producteur, user Face
     */
    private function parties(MissionPaymentCandidature $entry): array
    {
        $entry->loadMissing(['face.user', 'missionPayment.mission.producer.user']);

        $mission = $entry->missionPayment?->mission;
        $producer = $mission?->producer;

        return [
            $mission,
            $entry->face,
            $producer instanceof Producer && $producer->user instanceof User ? $producer->user : null,
            $entry->face?->user instanceof User ? $entry->face->user : null,
        ];
    }

    private function faceName(?Face $face): string
    {
        $prenom = $face instanceof Face ? trim((string) $face->prenom) : '';

        return $prenom !== '' ? $prenom : 'La Face';
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function notifySafely(User $user, string $type, string $message, Mission $mission, string $space, MissionPaymentCandidature $entry, array $extra = []): void
    {
        try {
            Notification::create([
                'user_id' => $user->id,
                'type' => $type,
                'data' => array_merge([
                    'message' => $message,
                    'mission_id' => $mission->id,
                    'entry_id' => $entry->id,
                    'url' => "/{$space}/missions/{$mission->uuid}",
                ], $extra),
            ]);
        } catch (\Throwable $e) {
            Log::warning('MissionAttendanceDisputeNotifier: notification failed', [
                'user_id' => $user->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function mail(User $user, MissionAttendanceDisputedMail|MissionAttendanceDisputeResolvedMail $mailable): void
    {
        try {
            $email = trim((string) $user->email);

            if ($email !== '') {
                Mail::to($email)->queue($mailable);
            }
        } catch (\Throwable $e) {
            Log::warning('MissionAttendanceDisputeNotifier: mail queue failed', [
                'user_id' => $user->id,
                'mail' => $mailable::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
