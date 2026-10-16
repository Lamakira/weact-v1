<?php

declare(strict_types=1);

namespace App\Listeners\Mission;

use App\Enums\FaceSubscriptionTier;
use App\Enums\MissionGender;
use App\Enums\MissionType;
use App\Events\MissionPublished;
use App\Jobs\SendWebPush;
use App\Mail\NewMissionAvailableMail;
use App\Models\Face;
use App\Models\Mission;
use App\Models\User;
use App\Services\FaceEntitlementService;
use App\Services\Push\WebPushService;
use App\Support\MissionAnnouncement;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Nouvelle mission publiée pour la 1re fois :
 *  - push à toutes les Faces abonnées au push (tous paliers, Découverte incluse) ;
 *  - e-mail en avant-première, réservé aux Faces avec abonnement payant actif
 *    (mission standard : Starter/Pro/Élite ; UGC : Pro/Élite non suspendues).
 * Filtre genre (tous = tout le monde ; sexe inconnu = notifié) et plafond de
 * MAX_PER_DAY push et MAX_PER_DAY e-mails par Face et par jour métier.
 * Ne throw jamais : une erreur par destinataire est loggée et on continue.
 */
#[AsEventListener(event: MissionPublished::class)]
class NotifyFacesOfNewMission implements ShouldQueue
{
    public const MAX_PER_DAY = 3;

    private const CHUNK_SIZE = 200;

    public int $tries = 1;

    public int $timeout = 80;

    public bool $afterCommit = true;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        private readonly WebPushService $push,
        private readonly FaceEntitlementService $entitlements,
    ) {}

    public function handle(MissionPublished $event): void
    {
        try {
            $mission = Mission::query()->find($event->mission->getKey());

            // Garde au moment de l'exécution : publiée, non expirée, Producteur actif.
            if ($mission === null || ! $mission->isAcceptingCandidatures() || ! $mission->hasActiveProducer()) {
                return;
            }

            $this->sendPushes($mission);
            $this->sendEmails($mission);
        } catch (\Throwable $e) {
            Log::warning('New mission notifications failed', [
                'mission_id' => $event->mission->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendPushes(Mission $mission): void
    {
        if (! $this->push->isEnabled()) {
            return;
        }

        $payload = [
            'title' => MissionAnnouncement::pushTitle($mission),
            'body' => MissionAnnouncement::pushBody($mission),
            'url' => "/face/missions/{$mission->uuid}",
            'tag' => "new-mission-{$mission->uuid}",
            'ttl' => 86400,
        ];

        $sent = 0;
        $capped = 0;

        $query = $this->faceQuery($mission)
            ->whereHas('user', fn (Builder $u) => $u->whereHas('pushSubscriptions'))
            ->with('user:id,userable_id,userable_type');

        $query->chunkById(self::CHUNK_SIZE, function ($faces) use ($payload, &$sent, &$capped): void {
            foreach ($faces as $face) {
                try {
                    /** @var User|null $user */
                    $user = $face->user;
                    if ($user === null) {
                        continue;
                    }

                    if (! $this->reserve('push', $user->id)) {
                        $capped++;

                        continue;
                    }

                    try {
                        SendWebPush::dispatch($user->id, $payload);
                        $sent++;
                    } catch (\Throwable $e) {
                        $this->release('push', $user->id);
                        throw $e;
                    }
                } catch (\Throwable $e) {
                    Log::warning('New mission push failed for a Face', ['face_id' => $face->id, 'error' => $e->getMessage()]);
                }
            }
        }, 'faces.id', 'id');

        Log::debug('New mission pushes', ['mission_id' => $mission->id, 'sent' => $sent, 'skipped_daily_cap' => $capped]);
    }

    private function sendEmails(Mission $mission): void
    {
        $isUgc = $mission->type_mission === MissionType::Ugc;
        $sent = 0;
        $capped = 0;

        $query = $this->faceQuery($mission)
            ->whereHas('user', fn (Builder $u) => $u->whereNotNull('email_verified_at'))
            ->whereHas('activeSubscription')
            ->with(['user:id,email,userable_id,userable_type', 'activeSubscription']);

        $query->chunkById(self::CHUNK_SIZE, function ($faces) use ($mission, $isUgc, &$sent, &$capped): void {
            foreach ($faces as $face) {
                try {
                    /** @var User|null $user */
                    $user = $face->user;
                    $plan = $face->activeSubscription?->plan;
                    $email = trim((string) $user?->email);
                    if ($user === null || $plan === null || $email === '') {
                        continue;
                    }

                    $eligible = $isUgc
                        ? $this->entitlements->canAccessUgc($face)
                        : $this->entitlements->capabilities($face)->tier !== FaceSubscriptionTier::Free;
                    if (! $eligible) {
                        continue;
                    }

                    if (! $this->reserve('mail', $user->id)) {
                        $capped++;

                        continue;
                    }

                    try {
                        Mail::to($email)->queue(new NewMissionAvailableMail($mission, $face, $plan));
                        $sent++;
                    } catch (\Throwable $e) {
                        $this->release('mail', $user->id);
                        throw $e;
                    }
                } catch (\Throwable $e) {
                    Log::warning('New mission e-mail failed for a Face', ['face_id' => $face->id, 'error' => $e->getMessage()]);
                }
            }
        }, 'faces.id', 'id');

        Log::debug('New mission e-mails', ['mission_id' => $mission->id, 'sent' => $sent, 'skipped_daily_cap' => $capped]);
    }

    /**
     * Faces with an active account matching the mission's gender preference
     * (unknown sexe is notified). Narrow columns only.
     *
     * @return Builder<Face>
     */
    private function faceQuery(Mission $mission): Builder
    {
        $query = Face::query()
            ->select(['faces.id', 'faces.prenom', 'faces.nom', 'faces.sexe'])
            ->whereHas('user', fn (Builder $u) => $u->where('is_active', true));

        $wanted = match ($mission->genre_voulu) {
            MissionGender::Homme => 'homme',
            MissionGender::Femme => 'femme',
            default => null,
        };

        if ($wanted !== null) {
            $query->where(fn (Builder $q) => $q->whereNull('faces.sexe')->orWhere('faces.sexe', $wanted));
        }

        return $query;
    }

    /**
     * Atomically reserve one slot of the daily cap (business date) for this
     * channel; false when the cap is reached. Released if the send fails, so
     * only actual sends count.
     */
    private function reserve(string $channel, int $userId): bool
    {
        $key = $this->capKey($channel, $userId);
        $tz = (string) config('app.business_timezone');

        Cache::add($key, 0, Carbon::now($tz)->endOfDay()->addHour());

        if ((int) Cache::increment($key) > self::MAX_PER_DAY) {
            Cache::decrement($key);

            return false;
        }

        return true;
    }

    private function release(string $channel, int $userId): void
    {
        Cache::decrement($this->capKey($channel, $userId));
    }

    private function capKey(string $channel, int $userId): string
    {
        $date = Carbon::now((string) config('app.business_timezone'))->toDateString();

        return "mission-notif:{$channel}:{$userId}:{$date}";
    }
}
