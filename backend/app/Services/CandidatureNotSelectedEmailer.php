<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\CandidatureNotSelectedMail;
use App\Models\Face;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Lot D5 : met en file l'e-mail « candidature non retenue » (rejet automatique après paiement,
 * expiration faute de réponse). Jamais bloquant : une panne d'envoi est loguée et rien d'autre.
 * L'appelant doit l'invoquer après commit.
 */
class CandidatureNotSelectedEmailer
{
    public function send(int $faceId, string $missionTitle, string $reason): void
    {
        try {
            /** @var User|null $faceUser */
            $faceUser = User::query()
                ->with('userable')
                ->where('userable_type', Face::class)
                ->where('userable_id', $faceId)
                ->first();

            $email = trim((string) $faceUser?->email);

            if ($faceUser === null || $email === '') {
                return;
            }

            $face = $faceUser->userable;

            Mail::to($email)->queue(new CandidatureNotSelectedMail(
                faceName: $face instanceof Face ? trim((string) $face->prenom) : '',
                missionTitle: $missionTitle,
                reason: $reason,
                browseUrl: rtrim((string) config('app.frontend_url'), '/').'/face/missions',
            ));
        } catch (\Throwable $e) {
            Log::warning('CandidatureNotSelectedMail queue failed', [
                'face_id' => $faceId,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
