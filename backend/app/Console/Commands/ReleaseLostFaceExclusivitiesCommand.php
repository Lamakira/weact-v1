<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\FaceSubscriptionTier;
use App\Models\Face;
use App\Notifications\FaceExclusivitySlotFreedNotification;
use App\Services\FaceExclusivityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

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

        foreach ($recipients as $email) {
            Notification::route('mail', $email)->notify(new FaceExclusivitySlotFreedNotification(
                $face->id,
                $face->display_name,
                $tier->label(),
            ));
        }
    }
}
