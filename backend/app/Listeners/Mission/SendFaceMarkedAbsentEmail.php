<?php

declare(strict_types=1);

namespace App\Listeners\Mission;

use App\Events\MissionAttendanceMarkedAbsent;
use App\Mail\FaceMarkedAbsentMail;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: MissionAttendanceMarkedAbsent::class)]
final class SendFaceMarkedAbsentEmail
{
    public function handle(MissionAttendanceMarkedAbsent $event): void
    {
        try {
            $entry = $event->entry;
            $entry->loadMissing(['face.user', 'missionPayment.mission.producer']);

            $face = $entry->face;
            $mission = $entry->missionPayment?->mission;
            $producer = $mission?->producer;

            if (! $face instanceof Face || ! $mission || ! $producer instanceof Producer) {
                Log::warning('FaceMarkedAbsentMail skipped - incomplete relations', [
                    'entry_id' => $entry->id,
                ]);

                return;
            }

            if ($entry->notified_at === null) {
                Log::warning('FaceMarkedAbsentMail skipped - notified_at is null', [
                    'entry_id' => $entry->id,
                ]);

                return;
            }

            /** @var User|null $faceUser */
            $faceUser = $face->user;

            if (! $faceUser) {
                return;
            }

            $faceEmail = trim((string) $faceUser->email);
            if ($faceEmail === '') {
                return;
            }

            Mail::to($faceEmail)->queue(new FaceMarkedAbsentMail(
                face: $face,
                mission: $mission,
                producer: $producer,
                amount: (int) $entry->montant_face_recoit,
                disputeDeadline: $entry->notified_at->copy()->addHours(72),
            ));
        } catch (\Throwable $e) {
            Log::warning('FaceMarkedAbsentMail queue failed', [
                'entry_id' => $event->entry->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
