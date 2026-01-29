<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MissionStatus;
use App\Enums\ProducerType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Producer extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'agency_name',
        'first_name',
        'last_name',
        'profile_photo',
        'profile_photo_thumbnail',
        'bio',
        'agency_logo',
        'agency_logo_thumbnail',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'profile_photo_url',
        'thumbnail_url',
        'display_name',
        'agency_logo_url',
        'agency_logo_thumbnail_url',
        'average_rating',
        'ratings_count',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProducerType::class,
        ];
    }

    /**
     * Get the user associated with this Producer.
     */
    public function user(): MorphOne
    {
        return $this->morphOne(User::class, 'userable');
    }

    /**
     * Get the missions created by this producer.
     */
    public function missions(): HasMany
    {
        return $this->hasMany(Mission::class);
    }

    /**
     * Check if this producer is an agency.
     */
    public function isAgency(): bool
    {
        return $this->type === ProducerType::Agency;
    }

    /**
     * Check if this producer is a particulier (individual).
     */
    public function isParticulier(): bool
    {
        return $this->type === ProducerType::Particulier;
    }

    /**
     * Get the display name for this producer.
     * Returns agency_name for agencies, or "first_name last_name" for particuliers.
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->type === ProducerType::Agency
                ? ($this->agency_name ?? '')
                : trim("{$this->first_name} {$this->last_name}"),
        );
    }

    /**
     * Get the full URL for the profile photo.
     */
    protected function profilePhotoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->profile_photo
                ? asset('storage/avatars/producers/' . $this->profile_photo)
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
                ? asset('storage/avatars/producers/thumbnails/' . $this->profile_photo_thumbnail)
                : null,
        );
    }

    /**
     * Get the full URL for the agency logo.
     */
    protected function agencyLogoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->agency_logo
                ? asset('storage/logos/agencies/' . $this->agency_logo)
                : null,
        );
    }

    /**
     * Get the full URL for the agency logo thumbnail.
     */
    protected function agencyLogoThumbnailUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->agency_logo_thumbnail
                ? asset('storage/logos/agencies/thumbnails/' . $this->agency_logo_thumbnail)
                : null,
        );
    }

    /**
     * Get the count of published missions for this producer.
     *
     * Only counts missions with MissionStatus::Published status.
     * Excludes draft, closed, and completed missions.
     */
    protected function publishedMissionsCount(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->missions()->where('status', MissionStatus::Published)->count(),
        );
    }

    /**
     * Get the ratings received by this Producer.
     */
    public function ratingsReceived(): MorphMany
    {
        return $this->morphMany(Rating::class, 'rated');
    }

    /**
     * Get the average rating score for this Producer.
     */
    protected function averageRating(): Attribute
    {
        return Attribute::make(
            get: function (): ?float {
                $avg = $this->ratingsReceived()->avg('score');

                return $avg !== null ? (float) $avg : null;
            },
        );
    }

    /**
     * Get the total count of ratings received by this Producer.
     */
    protected function ratingsCount(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->ratingsReceived()->count(),
        );
    }
}

