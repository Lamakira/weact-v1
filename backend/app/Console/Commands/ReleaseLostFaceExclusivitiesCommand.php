<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\FaceSubscriptionTier;
use App\Models\Face;
use App\Notifications\FaceExclusivitySlotFreedNotification;
use App\Services\FaceEntitlementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ReleaseLostFaceExclusivitiesCommand extends Command
{
    protected $signature = 'exclusivity:release-lost';

    protected $description = 'Retire l\'exclusivité Élite des Faces qui ne sont plus Élite en direct et alerte les admins qu\'une place est libre.';

    public function handle(FaceEntitlementService $entitlement): int
    {
        $released = 0;

        Face::query()
            ->where('is_exclusive', true)
            ->orderBy('id')
            ->each(function (Face $face) use ($entitlement, &$released): void {
                $tier = $entitlement->capabilities($face)->tier;

                if ($tier === FaceSubscriptionTier::Elite) {
                    return;
                }

                // Conditional write: a concurrent run or admin edit that already
                // cleared the flag affects 0 rows, so no duplicate mail.
                $cleared = Face::query()
                    ->whereKey($face->id)
                    ->where('is_exclusive', true)
                    ->update(['is_exclusive' => false]);

                if ($cleared === 0) {
                    return;
                }

                $released++;
                Log::info('Face exclusivity released: no longer Elite', [
                    'face_id' => $face->id,
                    'tier' => $tier->value,
                ]);
                $this->notifyAdmins($face, $tier);
            });

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
