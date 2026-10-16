<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\EscrowStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\Candidature;
use App\Models\Experience;
use App\Models\Face;
use App\Models\FacePhoto;
use App\Models\FaceSubscription;
use App\Models\FaceVideo;
use App\Models\Message;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\Rating;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Services\AgencyLogoService;
use App\Services\Auth\GoogleOAuthService;
use App\Services\FaceVideoService;
use App\Services\PhotoAlbumService;
use App\Services\PresentationVideoService;
use App\Services\ProducerProfilePhotoService;
use App\Services\ProfilePhotoService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserDataController extends Controller
{
    /** Replacement text for every message the deleted user authored. */
    private const DELETED_MESSAGE_PLACEHOLDER = '[message supprimé]';

    /**
     * Export all personal data for the authenticated user (Art. 438 - Portabilite).
     */
    public function export(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('userable');

        $data = [
            'export_date' => now()->toIso8601String(),
            'export_version' => '1.0',
            'account' => [
                'email' => $user->email,
                'created_at' => $user->created_at?->toIso8601String(),
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'consent_given_at' => $user->consent_given_at?->toIso8601String(),
                'consent_version' => $user->consent_version,
                'consent_ip' => $user->consent_ip,
                'balance' => (int) $user->balance,
                'has_password' => $user->password !== null,
                'google_linked' => $user->google_id !== null,
                'google_linked_at' => $user->google_linked_at?->toIso8601String(),
            ],
        ];

        if ($user->userable_type === Face::class && $user->userable) {
            /** @var Face $face */
            $face = $user->userable;
            $data['profile'] = [
                'type' => 'Face',
                'nom' => $face->nom,
                'prenom' => $face->prenom,
                'username' => $face->username,
                'sexe' => $face->sexe?->value,
                'date_naissance' => $face->date_naissance?->toDateString(),
                'nationalite' => $face->nationalite,
                'pays' => $face->pays,
                'ville' => $face->ville,
                'bio' => $face->bio,
                'whatsapp_number' => $face->whatsapp_number,
                'taille' => $face->taille,
                'poids' => $face->poids,
                'langues' => $face->langues,
                'categories' => $face->categories,
                'niches' => $face->niches,
                'tarif_horaire' => $face->tarif_horaire,
                'tarif_journalier' => $face->tarif_journalier,
                'is_available' => $face->is_available,
                'profile_photo_url' => $face->profile_photo_url,
                'presentation_video_url' => $face->presentation_video_url,
            ];

            $data['media'] = [
                'photos' => $face->photos()->get()->map(fn (FacePhoto $p) => $p->photo_url)->filter()->values()->all(),
                'videos' => $face->videos()->get()->map(fn (FaceVideo $v) => $v->video_url)->filter()->values()->all(),
            ];

            /** @var EloquentCollection<int, FaceSubscription> $subscriptions */
            $subscriptions = $face->subscriptions()->get();
            $data['subscriptions'] = $subscriptions->map(fn (FaceSubscription $sub) => [
                'plan' => $sub->plan->value,
                'status' => $sub->status->value,
                'starts_at' => $sub->starts_at?->toIso8601String(),
                'expires_at' => $sub->expires_at?->toIso8601String(),
                'cancelled_at' => $sub->cancelled_at?->toIso8601String(),
                'paid_amount' => $sub->paid_amount,
                'paid_at' => $sub->paid_at?->toIso8601String(),
                'currency' => $sub->currency,
                'created_at' => $sub->created_at?->toIso8601String(),
            ])->all();

            // Experiences
            /** @var EloquentCollection<int, Experience> $experiences */
            $experiences = $face->experiences()->get();
            $data['experiences'] = $experiences->map(fn (Experience $exp) => [
                'titre' => $exp->titre,
                'description' => $exp->description,
                'date_debut' => $exp->date_debut?->toDateString(),
                'date_fin' => $exp->date_fin?->toDateString(),
            ])->toArray();

            // Candidatures
            /** @var EloquentCollection<int, Candidature> $candidatures */
            $candidatures = $face->candidatures()
                ->with('mission:id,titre')
                ->get();
            $data['candidatures'] = $candidatures->map(fn (Candidature $c) => [
                'mission' => $c->mission?->titre,
                'status' => $c->status,
                'created_at' => $c->created_at?->toIso8601String(),
            ])->toArray();
        }

        if ($user->userable_type === Producer::class && $user->userable) {
            /** @var Producer $producer */
            $producer = $user->userable;
            $data['profile'] = [
                'type' => 'Producer',
                'producer_type' => $producer->type,
                'agency_name' => $producer->agency_name,
                'first_name' => $producer->first_name,
                'last_name' => $producer->last_name,
                'bio' => $producer->bio,
                'whatsapp_number' => $producer->whatsapp_number,
                'profile_photo_url' => $producer->profile_photo_url,
                'agency_logo_url' => $producer->agency_logo_url,
            ];

            /** @var EloquentCollection<int, MissionPayment> $missionPayments */
            $missionPayments = MissionPayment::query()->where('producer_id', $producer->id)->get();
            $data['mission_payments'] = $missionPayments->map(fn (MissionPayment $mp) => [
                'montant_total_producteur' => $mp->montant_total_producteur,
                'status' => $mp->status->value,
                'paid_at' => $mp->paid_at?->toIso8601String(),
            ])->all();

            // Missions
            /** @var EloquentCollection<int, Mission> $missions */
            $missions = $producer->missions()->get();
            $data['missions'] = $missions->map(fn (Mission $m) => [
                'titre' => $m->titre,
                'status' => $m->status,
                'created_at' => $m->created_at?->toIso8601String(),
            ])->toArray();
        }

        // Bookings
        $bookingsRelation = $user->userable_type === Face::class
            ? $user->bookingsAsFace()
            : $user->bookingsAsProducer();

        /** @var EloquentCollection<int, Booking> $bookings */
        $bookings = $bookingsRelation->get();
        $data['bookings'] = $bookings->map(fn (Booking $b) => [
            'status' => $b->status,
            'amount' => $user->userable_type === Face::class ? $b->montant_face_recoit : $b->montant_total_producteur,
            'created_at' => $b->created_at?->toIso8601String(),
        ])->toArray();

        // Ratings given
        /** @var EloquentCollection<int, Rating> $ratingsGiven */
        $ratingsGiven = $user->ratingsGiven()->get();
        $data['ratings_given'] = $ratingsGiven->map(fn (Rating $r) => [
            'score' => $r->score,
            'comment' => $r->comment,
            'created_at' => $r->created_at?->toIso8601String(),
        ])->toArray();

        // Messages: only those of bookings / candidatures the user is a party to.
        // The counterpart is never identified (no name, email or phone).
        /** @var EloquentCollection<int, BookingMessage> $bookingMessages */
        $bookingMessages = BookingMessage::query()
            ->whereHas('booking', fn ($q) => $q->where('face_id', $user->id)->orWhere('producer_id', $user->id))
            ->with('booking:id,uuid')
            ->orderBy('id')
            ->get();
        $data['booking_messages'] = $bookingMessages->map(fn (BookingMessage $m) => [
            'booking' => $m->booking?->uuid,
            'from_me' => $m->sender_id === $user->id,
            'content' => $m->content,
            'created_at' => $m->created_at->toIso8601String(),
        ])->all();

        /** @var EloquentCollection<int, Message> $conversationMessages */
        $conversationMessages = Message::query()
            ->whereHas('conversation.candidature', fn ($q) => $this->scopeCandidaturesOfUser($q, $user))
            ->with('conversation:id,uuid')
            ->orderBy('id')
            ->get();
        $data['conversation_messages'] = $conversationMessages->map(fn (Message $m) => [
            'conversation' => $m->conversation?->uuid,
            'from_me' => $m->sender_id === $user->id && $m->sender_type === User::class,
            'content' => $m->content,
            'created_at' => $m->created_at->toIso8601String(),
        ])->all();

        /** @var EloquentCollection<int, WalletTransaction> $walletTransactions */
        $walletTransactions = $user->walletTransactions()->orderBy('id')->get();
        $data['wallet_transactions'] = $walletTransactions->map(fn (WalletTransaction $t) => [
            'type' => $t->type,
            'amount' => $t->amount,
            'reference' => $t->reference,
            'description' => $t->description,
            'status' => $t->status,
            'created_at' => $t->created_at->toIso8601String(),
        ])->all();

        $data['withdrawal_requests'] = WithdrawalRequest::query()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get()
            ->map(fn (WithdrawalRequest $w) => [
                'amount' => $w->amount,
                'payment_mode' => $w->payment_mode,
                'phone_number' => $w->phone_number,
                'phone_country' => $w->phone_country,
                'status' => $w->status,
                'created_at' => $w->created_at?->toIso8601String(),
                'processed_at' => $w->processed_at?->toIso8601String(),
            ])->all();

        $data['notifications'] = Notification::query()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get()
            ->map(fn (Notification $n) => [
                'type' => $n->type,
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ])->all();

        return response()->json([
            'data' => $data,
            'message' => 'Export de vos données personnelles',
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Delete (anonymize) the authenticated user's account (Art. 443 - Droit a l'oubli).
     *
     * Refused (409) while money is still in play (wallet balance, pending withdrawal,
     * locked escrow, booking awaiting settlement or dispute).
     *
     * Personal data is erased or neutralized (profile, contact details, consent IP,
     * authored messages replaced by a placeholder, withdrawal phone numbers masked).
     * Financial records (wallet_transactions, withdrawal_requests rows, payments,
     * escrow, bookings amounts, subscriptions) are deliberately KEPT intact for
     * legal/accounting retention; they no longer point at any readable identity.
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        // Money guard, BEFORE any credential check or ticket consumption: anonymizing
        // an account that still holds or is owed money would strand funds the
        // platform must be able to attribute and pay out.
        $blockers = $this->deletionBlockers($user);
        if ($blockers !== []) {
            return response()->json([
                'error' => [
                    'code' => 'ACCOUNT_DELETION_BLOCKED',
                    'message' => implode(' ', $blockers),
                ],
            ], 409);
        }

        // Erasure (Art. 443) is confirmed either with the password, or — for an
        // account created through Google, which has none — with a fresh
        // re-authentication ticket. Making erasure conditional on first setting a
        // credential the user declined would be the wrong trade.
        $reauthToken = $request->validate([
            'reauth_token' => ['nullable', 'string'],
        ])['reauth_token'] ?? null;

        if (is_string($reauthToken) && $reauthToken !== '') {
            if (! app(GoogleOAuthService::class)->consumeReauthToken($reauthToken, $user->id)) {
                return response()->json([
                    'error' => [
                        'code' => 'REAUTH_TOKEN_INVALID',
                        'message' => 'Confirmation expirée. Reprenez la confirmation avec Google.',
                    ],
                ], 422);
            }
        } elseif ($user->password === null) {
            // Nothing to check a password against: point at the re-authentication
            // instead of a misleading "password required".
            return response()->json([
                'error' => [
                    'code' => 'ACCOUNT_DELETION_REQUIRES_REAUTH',
                    'message' => 'Confirmez avec Google, ou définissez un mot de passe, pour supprimer votre compte.',
                ],
            ], 403);
        } else {
            $validated = $request->validate([
                'password' => ['required', 'string'],
            ], [
                'password.required' => 'Le mot de passe est requis pour confirmer la suppression.',
            ]);

            if (! Hash::check($validated['password'], $user->password)) {
                return response()->json([
                    'error' => [
                        'code' => 'invalid_password',
                        'message' => 'Le mot de passe est incorrect.',
                    ],
                ], 422);
            }
        }

        // Anonymize DB first (atomic), then delete media files after success.
        // This avoids partial-failure data loss: if DB fails, media is untouched.
        DB::transaction(function () use ($user): void {
            if ($user->userable_type === Face::class && $user->userable) {
                /** @var Face $face */
                $face = $user->userable;
                $face->update([
                    'nom' => 'Utilisateur',
                    'prenom' => 'Supprimé',
                    'username' => 'deleted_'.$user->id.'_'.Str::random(6),
                    'bio' => null,
                    'whatsapp_number' => null,
                    'ville' => null,
                    'sexe' => null,
                    'date_naissance' => null,
                    'nationalite' => null,
                    'pays' => null,
                    'taille' => null,
                    'poids' => null,
                    'langues' => null,
                    'categories' => [],
                    'niches' => [],
                    'tarif_horaire' => null,
                    'tarif_journalier' => null,
                    'is_available' => false,
                ]);

                $face->experiences()->delete();
            }

            if ($user->userable_type === Producer::class && $user->userable) {
                /** @var Producer $producer */
                $producer = $user->userable;
                $producer->update([
                    'agency_name' => $producer->agency_name ? 'Compte supprimé' : null,
                    'first_name' => $producer->first_name ? 'Utilisateur' : null,
                    'last_name' => $producer->last_name ? 'Supprimé' : null,
                    'bio' => null,
                    'whatsapp_number' => null,
                ]);

                // The model's `updating` hook regenerates the slug from the (now
                // anonymized) name: overwrite it afterwards with a neutral one.
                Producer::query()->whereKey($producer->id)->update(['slug' => 'deleted-'.$producer->id]);
            }

            // Messages authored by the user. Rows are kept so the counterpart's
            // thread stays coherent; only the content is replaced.
            BookingMessage::query()
                ->where('sender_id', $user->id)
                ->update(['content' => self::DELETED_MESSAGE_PLACEHOLDER]);
            Message::query()
                ->where('sender_id', $user->id)
                ->where('sender_type', User::class)
                ->update(['content' => self::DELETED_MESSAGE_PLACEHOLDER]);

            // Withdrawal rows stay (accounting); the payout phone number is masked.
            // Both columns are NOT NULL, hence neutral values rather than null.
            WithdrawalRequest::query()
                ->where('user_id', $user->id)
                ->update(['phone_number' => '[supprimé]', 'phone_country' => '--']);

            // Anonymize the user record
            $user->update([
                'email' => 'deleted_'.$user->id.'@anonymized.weact.bj',
                'password' => Hash::make(Str::random(40)),
                'pending_email' => null,
                'consent_ip' => null,
                'is_active' => false,
            ]);

            // Unlink the Google identity. The rewritten email already makes the
            // email-matching branch miss this row, but google_id is matched FIRST:
            // without this, re-signing in with the same Google account would land
            // straight back on the deleted row. forceFill because google_id is
            // deliberately not mass assignable.
            $user->forceFill(['google_id' => null, 'google_linked_at' => null])->save();

            // Revoke all tokens
            $user->tokens()->delete();

            // A deleted account must stop receiving web push on its devices.
            $user->pushSubscriptions()->delete();
        });

        $cleanupFailures = [];

        // Delete media files AFTER successful DB transaction (best-effort cleanup)
        if ($user->userable_type === Face::class && $user->userable) {
            /** @var Face $face */
            $face = $user->userable;

            try {
                app(ProfilePhotoService::class)->deleteProfilePhoto($face);
            } catch (\Throwable $e) {
                $cleanupFailures[] = 'photo de profil';
                Log::warning('Failed to delete face profile photo for deleted user '.$user->id.': '.$e->getMessage());
            }

            try {
                app(PresentationVideoService::class)->deletePresentationVideo($face);
            } catch (\Throwable $e) {
                $cleanupFailures[] = 'vidéo de présentation';
                Log::warning('Failed to delete presentation video for deleted user '.$user->id.': '.$e->getMessage());
            }

            foreach ($face->videos as $video) {
                /** @var FaceVideo $video */
                try {
                    app(FaceVideoService::class)->deleteVideo($video);
                } catch (\Throwable $e) {
                    $cleanupFailures[] = 'vidéo de portfolio';
                    Log::warning('Failed to delete face video '.$video->id.' for deleted user '.$user->id.': '.$e->getMessage());
                }
            }

            foreach ($face->photos as $photo) {
                /** @var FacePhoto $photo */
                try {
                    app(PhotoAlbumService::class)->deletePhoto($photo);
                } catch (\Throwable $e) {
                    $cleanupFailures[] = 'photo d\'album';
                    Log::warning('Failed to delete album photo '.$photo->id.' for deleted user '.$user->id.': '.$e->getMessage());
                }
            }
        }

        if ($user->userable_type === Producer::class && $user->userable) {
            /** @var Producer $producer */
            $producer = $user->userable;

            try {
                app(ProducerProfilePhotoService::class)->deleteProfilePhoto($producer);
            } catch (\Throwable $e) {
                $cleanupFailures[] = 'photo de profil producer';
                Log::warning('Failed to delete producer profile photo for deleted user '.$user->id.': '.$e->getMessage());
            }

            try {
                app(AgencyLogoService::class)->deleteLogo($producer);
            } catch (\Throwable $e) {
                $cleanupFailures[] = 'logo d\'agence';
                Log::warning('Failed to delete agency logo for deleted user '.$user->id.': '.$e->getMessage());
            }
        }

        // Clear any lingering stateful session so a deleted browser session
        // cannot keep authenticating as the anonymized account.
        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Votre compte a été supprimé avec succès. Vos données personnelles ont été anonymisées.',
            'warning' => empty($cleanupFailures)
                ? null
                : 'Votre compte a bien été supprimé, mais certains fichiers médias n\'ont pas pu être nettoyés immédiatement. Notre équipe a été notifiée.',
        ]);
    }

    /**
     * Reasons (French sentences) why the account cannot be deleted right now.
     *
     * @return list<string>
     */
    private function deletionBlockers(User $user): array
    {
        $blockers = [];

        $balance = (int) $user->balance;
        if ($balance > 0) {
            $blockers[] = 'Votre portefeuille contient encore '.number_format($balance, 0, ',', ' ').' FCFA : retirez-les avant de supprimer votre compte.';
        }

        $hasPendingWithdrawal = WithdrawalRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();
        if ($hasPendingWithdrawal) {
            $blockers[] = 'Une demande de retrait est en cours de traitement : attendez son traitement avant de supprimer votre compte.';
        }

        // Bookings.face_id / producer_id are users.id. Unsettled money = escrow still
        // locked, an absence/late-cancellation awaiting settlement, or an open dispute.
        $hasUnsettledBooking = Booking::query()
            ->where(fn ($q) => $q->where('face_id', $user->id)->orWhere('producer_id', $user->id))
            ->where(function ($q): void {
                $q->whereHas('escrowTransaction', fn ($e) => $e->where('status', EscrowStatus::Locked->value))
                    ->orWhere(fn ($b) => $b->pendingSettlement())
                    ->orWhere(fn ($b) => $b->whereNotNull('disputed_at')->whereNull('dispute_resolved_at'));
            })
            ->exists();

        // Mission / hybrid UGC candidature escrow (face_id = faces.id, producer_id = producers.id).
        $hasLockedCandidatureEscrow = false;
        if ($user->userable_type === Face::class && $user->userable_id !== null) {
            $hasLockedCandidatureEscrow = MissionPaymentCandidature::query()
                ->where('face_id', $user->userable_id)
                ->where('escrow_status', EscrowStatus::Locked->value)
                ->exists();
        } elseif ($user->userable_type === Producer::class && $user->userable_id !== null) {
            $hasLockedCandidatureEscrow = MissionPaymentCandidature::query()
                ->where('escrow_status', EscrowStatus::Locked->value)
                ->whereHas('missionPayment', fn ($q) => $q->where('producer_id', $user->userable_id))
                ->exists();
        }

        if ($hasUnsettledBooking || $hasLockedCandidatureEscrow) {
            $blockers[] = 'Un booking ou une mission est en cours avec de l\'argent en jeu : terminez-le ou contactez le support.';
        }

        return $blockers;
    }

    /**
     * Restrict a Candidature query to those the user is a party to
     * (Face: candidatures.face_id = faces.id; Producer: owner of the mission).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private function scopeCandidaturesOfUser($query, User $user): void
    {
        if ($user->userable_type === Face::class) {
            $query->where('face_id', $user->userable_id);
        } elseif ($user->userable_type === Producer::class) {
            $query->whereHas('mission', fn ($q) => $q->where('producer_id', $user->userable_id));
        } else {
            $query->whereRaw('1 = 0');
        }
    }
}
