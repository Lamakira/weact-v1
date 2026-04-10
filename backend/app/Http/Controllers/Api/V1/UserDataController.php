<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Experience;
use App\Models\Face;
use App\Models\FacePhoto;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\Rating;
use App\Services\ActingVideoService;
use App\Services\AgencyLogoService;
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
            ];

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
            ];

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

        return response()->json([
            'data' => $data,
            'message' => 'Export de vos données personnelles',
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Delete (anonymize) the authenticated user's account (Art. 443 - Droit a l'oubli).
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ], [
            'password.required' => 'Le mot de passe est requis pour confirmer la suppression.',
        ]);

        $user = $request->user();

        if (! Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'error' => [
                    'code' => 'invalid_password',
                    'message' => 'Le mot de passe est incorrect.',
                ],
            ], 422);
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
                ]);
            }

            // Anonymize the user record
            $user->update([
                'email' => 'deleted_'.$user->id.'@anonymized.weact.bj',
                'password' => Hash::make(Str::random(40)),
                'pending_email' => null,
                'is_active' => false,
            ]);

            // Revoke all tokens
            $user->tokens()->delete();
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

            try {
                app(ActingVideoService::class)->deleteActingVideo($face);
            } catch (\Throwable $e) {
                $cleanupFailures[] = 'vidéo d\'acting';
                Log::warning('Failed to delete acting video for deleted user '.$user->id.': '.$e->getMessage());
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
}
