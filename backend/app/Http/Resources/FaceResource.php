<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\FaceVideoType;
use App\Models\Admin;
use App\Models\Face;
use App\Models\FaceVideo;
use App\Services\FaceEntitlementService;
use App\ValueObjects\TierCapabilities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Face
 */
class FaceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->user;

        $entitlement = app(FaceEntitlementService::class);
        $capabilities = $entitlement->capabilities($this->resource);
        $viewer = $this->resolveViewerContext($request);
        $isPrivileged = $viewer === 'owner' || $viewer === 'admin';

        return [
            'id' => $this->uuid,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'username' => $this->username,
            'sexe' => $this->sexe?->value,
            'sexe_label' => $this->sexe?->label(),
            'age' => $this->resolveAge($request),
            'nationalite' => $this->nationalite,
            'langues' => $this->langues,
            'profile_photo_url' => $this->profile_photo_url,
            'thumbnail_url' => $this->thumbnail_url,
            // 400px card variant (server falls back grid → medium → original)
            'profile_photo_grid_url' => $this->grid_url,
            'presentation_video_url' => ($isPrivileged || $capabilities->maxPresentationVideos >= 1)
                ? $this->presentation_video_url
                : null,
            'presentation_video_thumbnail_url' => ($isPrivileged || $capabilities->maxPresentationVideos >= 1)
                ? $this->presentation_video_thumbnail_url
                : null,
            ...($isPrivileged ? [
                'is_presentation_video_locked' => $this->presentation_video !== null
                    && $capabilities->maxPresentationVideos < 1,
                'presentation_video_lock_reason' => ($this->presentation_video !== null
                    && $capabilities->maxPresentationVideos < 1) ? 'tier_below_required' : null,
            ] : []),
            'videos' => $this->whenLoaded('videos', fn () => $this->maskVideos($capabilities, $isPrivileged)),
            'bio' => $this->bio,
            'ville' => $this->ville,
            'pays' => $this->pays,
            // OWASP A02: WhatsApp number is PII — only the owner/admin may see it, never
            // any authenticated Producer (would allow mass off-platform contact harvesting).
            'whatsapp_number' => $isPrivileged ? $this->whatsapp_number : null,
            'formatted_location' => $this->formatted_location,
            'taille' => $this->taille,
            'poids' => $this->poids,
            'categories' => $this->categoriesWithLabels(),
            'niches' => $this->nichesWithLabels(),
            'tarif_horaire' => $this->tarif_horaire,
            'tarif_journalier' => $this->tarif_journalier,
            'formatted_tarif_horaire' => $this->formatted_tarif_horaire,
            'formatted_tarif_journalier' => $this->formatted_tarif_journalier,
            'is_available' => $this->is_available,
            'is_featured' => $this->is_featured,
            'has_elite_badge' => $capabilities->hasEliteBadge,
            ...($viewer === 'admin' ? [
                'subscription_tier' => $capabilities->tier->value,
            ] : []),
            'availability_badge' => $this->availability_badge,
            'availability_badge_color' => $this->availability_badge_color,
            'profile_completion_percentage' => $this->profile_completion_percentage,
            'profile_completion_missing' => $this->profile_completion_missing,
            'profile_completion_is_complete' => $this->profile_completion_is_complete,
            'average_rating' => $this->average_rating,
            'ratings_count' => $this->ratings_count,
            'email' => $this->whenLoaded('user', fn () => $user?->email),
            'is_active' => $this->whenLoaded('user', fn () => $user?->is_active),
            'experiences' => ExperienceResource::collection($this->whenLoaded('experiences')),
            'experiences_count' => $this->experiences_count,
            'photos' => $isPrivileged
                ? $this->whenLoaded('photos', fn () => $this->photos->map(function ($photo) use ($capabilities) {
                    $locked = $photo->position > $capabilities->maxAlbumPhotos;

                    return [
                        'id' => $photo->uuid,
                        'photo_url' => $photo->photo_url,
                        'medium_url' => $photo->medium_url,
                        'grid_url' => $photo->grid_url,
                        'large_url' => $photo->large_url,
                        'thumbnail_url' => $photo->thumbnail_url,
                        'position' => $photo->position,
                        'is_locked' => $locked,
                        'lock_reason' => $locked ? 'quota_exceeded' : null,
                    ];
                })->values()->all())
                : $this->whenLoaded('photos', fn () => $this->photos
                    ->filter(fn ($photo) => $photo->position <= $capabilities->maxAlbumPhotos)
                    ->values()
                    ->map(fn ($photo) => [
                        'id' => $photo->uuid,
                        'photo_url' => $photo->photo_url,
                        'medium_url' => $photo->medium_url,
                        'grid_url' => $photo->grid_url,
                        'large_url' => $photo->large_url,
                        'thumbnail_url' => $photo->thumbnail_url,
                        'position' => $photo->position,
                    ])->all()),
            'photos_count' => $this->photos_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Resolve age visibility based on requesting user context.
     * Admins and the Face owner always see age. Others respect show_age.
     */
    private function resolveAge(Request $request): ?int
    {
        $user = $request->user();

        // Admin always sees age
        if ($user instanceof Admin) {
            return $this->age;
        }

        // Face owner always sees their own age
        if ($user && $user->userable_type === Face::class && $user->userable_id === $this->id) {
            return $this->age;
        }

        // Everyone else (producers) respects show_age
        return $this->show_age ? $this->age : null;
    }

    /**
     * Classify the requesting viewer for entitlement-aware masking.
     *
     * @return 'owner'|'admin'|'other'
     */
    private function resolveViewerContext(Request $request): string
    {
        $user = $request->user();

        if ($user instanceof Admin) {
            return 'admin';
        }

        if ($user && $user->userable_type === Face::class && $user->userable_id === $this->id) {
            return 'owner';
        }

        return 'other';
    }

    /**
     * Build the videos array: privileged viewers get every video with lock
     * metadata; others get only within-quota videos with no lock fields.
     *
     * @return list<array<string, mixed>>
     */
    private function maskVideos(TierCapabilities $capabilities, bool $isPrivileged): array
    {
        return $this->videos
            ->map(function (FaceVideo $video) use ($capabilities, $isPrivileged): ?array {
                $quota = $video->type === FaceVideoType::Acting
                    ? $capabilities->maxActingVideos
                    : $capabilities->maxUgcVideos;
                $locked = $video->position > $quota;

                if (! $isPrivileged && $locked) {
                    return null;
                }

                $item = [
                    'id' => $video->uuid,
                    'type' => $video->type->value,
                    'video_url' => $video->video_url,
                    'thumbnail_url' => $video->thumbnail_url,
                    'position' => $video->position,
                ];

                if ($isPrivileged) {
                    $item['is_locked'] = $locked;
                    $item['lock_reason'] = $locked
                        ? ($quota < 1 ? 'tier_below_required' : 'quota_exceeded')
                        : null;
                }

                return $item;
            })
            ->filter()
            ->values()
            ->all();
    }
}
