<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FaceCategory;
use App\Enums\FaceNiche;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Face extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nom',
        'prenom',
        'username',
        'profile_photo',
        'profile_photo_thumbnail',
        'presentation_video',
        'presentation_video_thumbnail',
        'acting_video',
        'acting_video_thumbnail',
        'bio',
        'ville',
        'quartier',
        'pays',
        'taille',
        'poids',
        'categorie',
        'niche',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'categorie' => FaceCategory::class,
        'niche' => FaceNiche::class,
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'profile_photo_url',
        'thumbnail_url',
        'presentation_video_url',
        'presentation_video_thumbnail_url',
        'acting_video_url',
        'acting_video_thumbnail_url',
        'formatted_location',
    ];

    /**
     * Get the user associated with this Face.
     */
    public function user(): MorphOne
    {
        return $this->morphOne(User::class, 'userable');
    }

    /**
     * Get the full URL for the profile photo.
     */
    protected function profilePhotoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->profile_photo
                ? asset('storage/avatars/faces/' . $this->profile_photo)
                : null,
        );
    }

    /**
     * Get the full URL for the profile photo thumbnail.
     */
    protected function thumbnailUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->profile_photo_thumbnail
                ? asset('storage/avatars/faces/thumbnails/' . $this->profile_photo_thumbnail)
                : null,
        );
    }

    /**
     * Get the album photos for this Face.
     */
    public function photos(): HasMany
    {
        return $this->hasMany(FacePhoto::class)->orderBy('position');
    }

    /**
     * Get the professional experiences for this Face.
     */
    public function experiences(): HasMany
    {
        return $this->hasMany(Experience::class);
    }

    /**
     * Get the full URL for the presentation video.
     */
    protected function presentationVideoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->presentation_video
                ? asset('storage/videos/faces/presentation/' . $this->presentation_video)
                : null,
        );
    }

    /**
     * Get the full URL for the presentation video thumbnail.
     */
    protected function presentationVideoThumbnailUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->presentation_video_thumbnail
                ? asset('storage/videos/faces/presentation/thumbnails/' . $this->presentation_video_thumbnail)
                : null,
        );
    }

    /**
     * Get the full URL for the acting video.
     */
    protected function actingVideoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->acting_video
                ? asset('storage/videos/faces/acting/' . $this->acting_video)
                : null,
        );
    }

    /**
     * Get the full URL for the acting video thumbnail.
     */
    protected function actingVideoThumbnailUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->acting_video_thumbnail
                ? asset('storage/videos/faces/acting/thumbnails/' . $this->acting_video_thumbnail)
                : null,
        );
    }

    /**
     * Get the formatted location string (Ville, Quartier, Pays).
     */
    protected function formattedLocation(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                $parts = array_filter([
                    $this->ville,
                    $this->quartier,
                    $this->pays,
                ]);

                return count($parts) > 0 ? implode(', ', $parts) : null;
            },
        );
    }
}
