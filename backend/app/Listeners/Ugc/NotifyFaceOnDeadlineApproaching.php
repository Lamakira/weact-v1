<?php

declare(strict_types=1);

namespace App\Listeners\Ugc;

use App\Events\UgcDeadlineApproaching;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Notification;
use App\Models\User;
use App\Services\Ugc\UgcDeadlineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Notifie la Face (in-app + Reverb) que l'échéance d'upload de son livrable
 * approche, avec une urgence par palier (4.5). Calque NotifyFaceOnShipmentConfirmed
 * (résolution Face booking/candidature, piège 2.4). Mis en file + rejoué (R1,
 * code-review 4.5) : la commande avance shipments.last_notified_threshold AVANT le
 * dispatch, donc un échec ne doit PAS être silencieux (sinon le palier — surtout le
 * dernier, sans filet — serait perdu). Un blip transitoire est rejoué et s'auto-
 * guérit ; un échec permanent atterrit dans failed_jobs (observable, pas perdu).
 */
#[AsEventListener(event: UgcDeadlineApproaching::class)]
class NotifyFaceOnDeadlineApproaching implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    /** @var array<int, int> Backoff progressif entre les rejeux (secondes). */
    public array $backoff = [10, 30];

    public function __construct(
        private readonly UgcDeadlineService $deadlines,
    ) {}

    public function handle(UgcDeadlineApproaching $event): void
    {
        try {
            $shipment = $event->shipment;
            $owner = $shipment->owner;

            if ($owner instanceof Booking) {
                $faceUserId = $owner->face_id; // users.id (piège 2.4)
                $productName = (string) $owner->nom_produit;
                $url = "/face/bookings/{$owner->uuid}";
            } elseif ($owner instanceof Candidature) {
                $owner->loadMissing('mission');
                $mission = $owner->mission;
                $faceUserId = User::where('userable_type', Face::class)
                    ->where('userable_id', $owner->face_id) // faces.id (piège 2.4)
                    ->value('id');
                $productName = (string) ($mission->nom_produit ?? '');
                $url = $mission !== null ? "/face/missions/{$mission->uuid}" : '/face/missions';
            } else {
                return;
            }

            if (! $faceUserId) {
                return;
            }

            $window = $this->deadlines->chronoWindowFor($shipment);
            if ($window === null) {
                return; // chrono refermé entre dispatch et handle
            }

            Notification::create([
                'user_id' => $faceUserId,
                'type' => 'ugc_deliverable_deadline_approaching',
                'data' => [
                    'message' => $this->message($event->level, $window['kind']->label(), $productName, $this->humanizeRemaining($window['deadline'])),
                    'level' => $event->level,
                    'kind' => $window['kind']->value,
                    'shipment_id' => $shipment->uuid,
                    'url' => $url,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('UgcDeadlineApproaching notification failed', [
                'shipment_id' => $event->shipment->id,
                'error' => $e->getMessage(),
            ]);

            throw $e; // R1 : laisse la queue rejouer (anti-perte du palier — cf. docblock).
        }
    }

    private function message(int $level, string $kindLabel, string $productName, string $remaining): string
    {
        return match (true) {
            $level >= 3 => "⏰ Dernière ligne droite : il te reste {$remaining} pour déposer ton {$kindLabel} « {$productName} », sinon ton compte sera suspendu.",
            $level === 2 => "Attention : il te reste {$remaining} pour déposer ton {$kindLabel} « {$productName} ». Ne tarde pas.",
            default => "Plus que {$remaining} pour déposer ton {$kindLabel} « {$productName} ». Pense à filmer !",
        };
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
