<?php

declare(strict_types=1);

namespace App\Listeners\Mission;

use App\Events\MissionAttendanceMarkedAbsent;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: MissionAttendanceMarkedAbsent::class)]
final class NotifyFaceOnAttendanceAbsence
{
    public function handle(MissionAttendanceMarkedAbsent $event): void
    {
        try {
            $entry = $event->entry;
            $entry->loadMissing(['face.user', 'missionPayment.mission.producer']);

            $mission = $entry->missionPayment?->mission;
            $producer = $mission?->producer;

            if (! $mission || ! $producer) {
                Log::warning('Mission attendance absent notification skipped - incomplete relations', [
                    'entry_id' => $entry->id,
                ]);

                return;
            }

            if ($entry->notified_at === null) {
                Log::warning('Mission attendance absent notification skipped - notified_at is null', [
                    'entry_id' => $entry->id,
                ]);

                return;
            }

            /** @var User|null $faceUser */
            $faceUser = $entry->face?->user;

            if (! $faceUser) {
                return;
            }

            $producerDisplayName = trim((string) ($producer->display_name ?? ''));
            $producerName = $producerDisplayName !== '' ? $producerDisplayName : 'Le Producer';

            Notification::create([
                'user_id' => $faceUser->id,
                'type' => 'mission_attendance_absent',
                'data' => [
                    'message' => "Le Producer {$producerName} vous a déclarée absente pour la mission «\u{a0}{$mission->titre}\u{a0}». Vous avez 72h pour contester.",
                    'mission_id' => $mission->id,
                    'entry_id' => $entry->id,
                    'url' => "/face/missions/{$mission->uuid}",
                    'dispute_deadline' => $entry->notified_at->copy()->addHours(72)->toIso8601String(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Mission attendance absent notification failed', [
                'entry_id' => $event->entry->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
