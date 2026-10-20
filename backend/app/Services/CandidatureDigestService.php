<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Mail\NewCandidaturesDigestMail;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Lot D5 : e-mail groupé au Producer pour les nouvelles candidatures d'une mission.
 * Au plus un e-mail par mission et par heure ; il résume les candidatures en attente créées
 * depuis le dernier envoi (missions.candidature_digest_sent_at). Retirées/traitées : non comptées.
 */
class CandidatureDigestService
{
    /** Délai minimal entre deux e-mails groupés d'une même mission. */
    public const MIN_INTERVAL_MINUTES = 60;

    /** Nombre maximal de Faces nommées dans l'e-mail. */
    public const MAX_FACES_LISTED = 5;

    /**
     * Missions encore en recrutement ayant potentiellement des candidatures à signaler.
     * Sur-ensemble SQL, la règle exacte (nouvelles candidatures) est revérifiée dans sendDigest().
     *
     * @return Builder<Mission>
     */
    public function eligibleMissions(): Builder
    {
        return Mission::query()
            ->where('status', MissionStatus::Published->value)
            ->where(function (Builder $query): void {
                $query->whereNull('candidature_digest_sent_at')
                    ->orWhere('candidature_digest_sent_at', '<=', now()->subMinutes(self::MIN_INTERVAL_MINUTES));
            })
            ->whereHas('candidatures', fn (Builder $query) => $query->where('status', CandidatureStatus::Pending->value))
            ->with('producer.user');
    }

    /**
     * Envoie le récapitulatif d'une mission si des candidatures en attente sont apparues depuis
     * le dernier envoi. Réservation atomique de l'horodatage (un passage concurrent ne renvoie rien),
     * restituée si l'envoi échoue pour qu'il soit retenté au prochain passage.
     */
    public function sendDigest(Mission $mission): bool
    {
        $previous = $mission->candidature_digest_sent_at;
        $now = now();

        if ($previous !== null && $previous->gt($now->copy()->subMinutes(self::MIN_INTERVAL_MINUTES))) {
            return false;
        }

        $pending = Candidature::query()
            ->where('mission_id', $mission->id)
            ->where('status', CandidatureStatus::Pending->value);

        $newQuery = (clone $pending)
            ->when($previous !== null, fn (Builder $query) => $query->where('created_at', '>', $previous));

        $newCount = (clone $newQuery)->count();

        if ($newCount === 0) {
            return false;
        }

        $producerUser = $mission->producer?->user;
        $email = $producerUser instanceof User ? trim((string) $producerUser->email) : '';

        if ($email === '') {
            return false;
        }

        $claimed = Mission::query()
            ->whereKey($mission->id)
            ->when(
                $previous === null,
                fn (Builder $query) => $query->whereNull('candidature_digest_sent_at'),
                fn (Builder $query) => $query->where('candidature_digest_sent_at', $previous),
            )
            ->update(['candidature_digest_sent_at' => $now]);

        if ($claimed !== 1) {
            return false;
        }

        try {
            $faces = (clone $newQuery)
                ->with('face')
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit(self::MAX_FACES_LISTED)
                ->get()
                ->map(static function (Candidature $candidature): array {
                    $face = $candidature->face;
                    $prenom = $face instanceof Face ? trim((string) $face->prenom) : '';
                    $name = $prenom !== '' ? $prenom : ($face instanceof Face ? (string) $face->display_name : 'Une Face');
                    $city = $face instanceof Face ? trim((string) $face->ville) : '';

                    return ['name' => $name, 'city' => $city !== '' ? $city : null];
                })
                ->values()
                ->all();

            Mail::to($email)->queue(new NewCandidaturesDigestMail(
                mission: $mission,
                newCount: $newCount,
                faces: $faces,
                totalPending: (clone $pending)->count(),
            ));
        } catch (\Throwable $e) {
            Mission::query()->whereKey($mission->id)->update(['candidature_digest_sent_at' => $previous]);

            Log::warning('NewCandidaturesDigestMail queue failed', [
                'mission_id' => $mission->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        return true;
    }
}
