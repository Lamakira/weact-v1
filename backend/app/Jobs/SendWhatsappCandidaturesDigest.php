<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Mission;
use App\Models\User;
use App\Services\Whatsapp\WhatsappService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Fermeture d'une fenêtre de regroupement des candidatures (6 h par mission et par Producteur) :
 * envoie le cumul des candidatures arrivées depuis le premier message. S'il y en a, un message part
 * et rouvre une fenêtre (donc un nouveau rendez-vous) ; sinon la fenêtre est simplement fermée.
 */
class SendWhatsappCandidaturesDigest implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    public function __construct(public readonly int $userId, public readonly int $missionId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(WhatsappService $whatsapp): void
    {
        [$windowKey, $pendingKey] = WhatsappService::candidatureKeys($this->userId, $this->missionId);
        $hours = (int) config('whatsapp.candidatures_window_hours', 6);

        $count = (int) Cache::pull($pendingKey, 0);
        $user = User::with('userable')->find($this->userId);
        $mission = Mission::find($this->missionId);
        $firstName = $user !== null ? $whatsapp->firstNameFor($user) : null;

        if ($count === 0 || $user === null || $mission === null || $firstName === null
            || ! $whatsapp->isAvailable() || ! $whatsapp->isOptedIn($user)) {
            Cache::forget($windowKey);

            return;
        }

        $result = $whatsapp->deliver(
            $user,
            'weact_new_candidatures',
            [$firstName, $whatsapp->paramFor($mission->titre), WhatsappService::candidaturesLabel($count)],
            WhatsappService::missionCandidaturesSuffix($mission),
            "new_candidatures:{$mission->id}",
        );

        if ($result === WhatsappService::RETRY) {
            // Le cumul est restitué pour la nouvelle tentative.
            Cache::put($pendingKey, $count, now()->addHours($hours * 2));

            throw new RuntimeException('WhatsApp weact_new_candidatures : échec transitoire, nouvelle tentative.');
        }

        // Le message envoyé compte comme celui de la fenêtre suivante.
        Cache::put($windowKey, true, now()->addHours($hours));
        self::dispatch($this->userId, $this->missionId)->delay(now()->addHours($hours));
    }
}
