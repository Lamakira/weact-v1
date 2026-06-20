<?php

declare(strict_types=1);

namespace App\Listeners\Ugc;

use App\Events\UgcDeadlineApproaching;
use App\Listeners\Ugc\Concerns\ResolvesUgcOwnerRecipients;
use App\Mail\UgcDeadlineApproachingMail;
use App\Services\Ugc\UgcDeadlineService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Canal email ADDITIF (ugc-7-3) sur UgcDeadlineApproaching : rappelle à la Face de filmer
 * avant l'échéance. 1 mail par palier (idempotence last_notified_threshold côté commande).
 * ⚠️ Contraste avec l'in-app NotifyFaceOnDeadlineApproaching (ShouldQueue + re-throw) : ICI
 * non-fatal SANS re-throw (D-7.3.d) — un mail de rappel raté ne doit JAMAIS planter le cron.
 */
#[AsEventListener(event: UgcDeadlineApproaching::class)]
final class SendUgcDeadlineApproachingEmail
{
    use ResolvesUgcOwnerRecipients;

    public function __construct(
        private readonly UgcDeadlineService $deadlines,
    ) {}

    public function handle(UgcDeadlineApproaching $event): void
    {
        try {
            $shipment = $event->shipment;
            $owner = $this->ugcOwnerFrom($shipment);
            $email = $this->faceEmailFor($owner);
            if ($email === '') {
                return;
            }

            $window = $this->deadlines->chronoWindowFor($shipment);
            if ($window === null) {
                return; // chrono refermé entre dispatch et handle (miroir in-app)
            }

            Mail::to($email)->queue(new UgcDeadlineApproachingMail(
                faceName: $this->faceNameFor($owner),
                productName: $this->productNameFor($owner),
                kindLabel: $window['kind']->label(),
                remaining: $this->humanizeRemaining($window['deadline']),
                level: $event->level,
                dealUrl: $this->faceDealUrlFor($owner),
            ));
        } catch (\Throwable $e) {
            Log::warning('UgcDeadlineApproachingMail queue failed', [
                'shipment_id' => $event->shipment->id,
                'error' => $e->getMessage(),
            ]);
            // PAS de re-throw (D-7.3.d) : le cron ne doit pas planter pour un mail.
        }
    }

    private function humanizeRemaining(Carbon $deadline): string
    {
        if ($deadline->isPast()) {
            return 'quelques heures';
        }
        $hours = (int) now()->diffInHours($deadline);
        if ($hours >= 24) {
            $days = intdiv($hours, 24);

            return $days.' '.($days > 1 ? 'jours' : 'jour');
        }

        return max(1, $hours).' '.($hours > 1 ? 'heures' : 'heure');
    }
}
