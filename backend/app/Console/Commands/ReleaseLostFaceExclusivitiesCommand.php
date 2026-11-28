<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\FaceSubscriptionTier;
use App\Models\Face;
use App\Notifications\FaceExclusivitySlotFreedNotification;
use App\Services\FaceExclusivityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class ReleaseLostFaceExclusivitiesCommand extends Command
{
    protected $signature = 'exclusivity:release-lost';

    protected $description = 'Retire l\'exclusivité Élite des Faces qui ne sont plus Élite en direct et alerte les admins qu\'une place est libre.';

    public function handle(FaceExclusivityService $exclusivity): int
    {
        $released = $exclusivity->releaseLostExclusivities(
            fn (Face $face, FaceSubscriptionTier $tier) => $this->notifyAdmins($face, $tier),
        );

        $this->info("Done. Released: {$released}.");

        return self::SUCCESS;
    }

    private function notifyAdmins(Face $face, FaceSubscriptionTier $tier): void
    {
        /** @var array<int, string> $recipients */
        $recipients = (array) config('alerts.critical.recipients', []);

        if ($recipients === []) {
            Log::warning('Face exclusivity released but no alert address is configured: nobody was told a slot is free', [
                'face_id' => $face->id,
                'tier' => $tier->value,
            ]);

            return;
        }

        foreach ($recipients as $email) {
            // A queueing failure must neither lose the alert silently nor stop
            // the other recipients and Faces: the flag is already cleared.
            try {
                Notification::route('mail', $email)->notify(new FaceExclusivitySlotFreedNotification(
                    $face->id,
                    $face->display_name,
                    $tier->label(),
                ));
            } catch (Throwable $e) {
                Log::critical('Face exclusivity slot-freed alert could not be sent', [
                    'face_id' => $face->id,
                    'tier' => $tier->value,
                    'recipient' => $email,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }
}
