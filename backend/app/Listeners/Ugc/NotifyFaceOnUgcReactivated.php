<?php

declare(strict_types=1);

namespace App\Listeners\Ugc;

use App\Events\FaceUgcReactivated;
use App\Models\Face;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Notifie la Face que son compte UGC est réactivé (épic 5, story 5.3) — quel que
 * soit le chemin (complétion tardive / appel accepté / admin). suspension.face_id
 * est faces.id → résolu en users.id (piège 2.4, calque NotifyFaceOnDeliverableValidated).
 */
#[AsEventListener(event: FaceUgcReactivated::class)]
class NotifyFaceOnUgcReactivated
{
    public function handle(FaceUgcReactivated $event): void
    {
        try {
            $faceUserId = User::where('userable_type', Face::class)
                ->where('userable_id', $event->suspension->face_id) // faces.id
                ->value('id');

            if (! $faceUserId) {
                return;
            }

            Notification::create([
                'user_id' => $faceUserId,
                'type' => 'ugc_account_reactivated',
                'data' => [
                    'message' => 'Ton compte UGC est réactivé — tu peux de nouveau accéder aux opportunités UGC.',
                    'url' => '/face/dashboard',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('FaceUgcReactivated notification failed', [
                'suspension_id' => $event->suspension->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
