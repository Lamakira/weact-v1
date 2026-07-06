<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasRouteUuid;
use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Enums\ProducerType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property ProducerType|string|null $type
 * @property string|null $agency_name
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $bio
 * @property string|null $slug
 * @property-read string $display_name
 * @property-read string|null $profile_photo_url
 * @property-read string|null $thumbnail_url
 * @property-read string|null $medium_url
 * @property-read string|null $grid_url
 * @property-read string|null $large_url
 * @property-read string|null $agency_logo_url
 * @property-read string|null $agency_logo_thumbnail_url
 * @property-read float|null $average_rating
 * @property-read int $ratings_count
 * @property float|int|string|null $ratings_received_avg_score
 * @property int|string|null $ratings_received_count
 * @property-read int $published_missions_count
 * @property-read int $completed_missions_count
 * @property-read int $in_progress_missions_count
 * @property-read \Illuminate\Support\Carbon|null $created_at
 * @property-read \Illuminate\Support\Carbon|null $updated_at
 */
class Producer extends Model
{
    use HasFactory, HasRouteUuid;

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Producer $producer): void {
            if (empty($producer->slug)) {
                $producer->slug = static::generateUniqueSlug($producer->slugSourceName());
            }
        });

        static::updating(function (Producer $producer): void {
            if ($producer->isDirty(['type', 'agency_name', 'first_name', 'last_name'])) {
                $producer->slug = static::generateUniqueSlug($producer->slugSourceName(), $producer->id);
            }
        });
    }

    public function slugSourceName(): string
    {
        if ($this->currentType() === ProducerType::Agency) {
            return (string) ($this->agency_name ?? '');
        }

        return trim((string) ($this->first_name ?? '').' '.(string) ($this->last_name ?? ''));
    }

    public function currentType(): ?ProducerType
    {
        $type = $this->getAttribute('type');

        if ($type instanceof ProducerType) {
            return $type;
        }

        return is_string($type) ? ProducerType::tryFrom($type) : null;
    }

    public static function generateUniqueSlug(string $sourceName, ?int $ignoreId = null): string
    {
        $base = Str::slug($sourceName);
        $slug = $base !== '' ? $base : 'producer';
        $counter = 1;

        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = ($base !== '' ? $base : 'producer')."-{$counter}";
            $counter++;
        }

        return $slug;
    }

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
        'profile_photo_medium',
        'profile_photo_grid',
        'profile_photo_large',
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
        'medium_url',
        'grid_url',
        'large_url',
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
        return $this->currentType() === ProducerType::Agency;
    }

    /**
     * Check if this producer is a particulier (individual).
     */
    public function isParticulier(): bool
    {
        return $this->currentType() === ProducerType::Particulier;
    }

    /**
     * Get the display name for this producer.
     * Returns agency_name for agencies, or "first_name last_name" for particuliers.
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->slugSourceName(),
        );
    }

    /**
     * Get the full URL for the profile photo.
     */
    protected function profilePhotoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->profile_photo
                ? asset('storage/avatars/producers/'.$this->profile_photo)
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
                ? asset('storage/avatars/producers/thumbnails/'.$this->profile_photo_thumbnail)
                : $this->profile_photo_url, // fallback to original while the variant job is pending
        );
    }

    /**
     * Get the full URL for the medium profile photo (800px wide WebP).
     */
    protected function mediumUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->profile_photo_medium
                ? asset('storage/avatars/producers/medium/'.$this->profile_photo_medium)
                : ($this->profile_photo
                    ? asset('storage/avatars/producers/'.$this->profile_photo)
                    : null),
        );
    }

    /**
     * Get the full URL for the grid profile photo (400px wide WebP).
     */
    protected function gridUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->profile_photo_grid
                ? asset('storage/avatars/producers/grid/'.$this->profile_photo_grid)
                : $this->medium_url, // fallback to medium then original (legacy rows / pending job)
        );
    }

    /**
     * Get the full URL for the large profile photo (1600px wide WebP).
     */
    protected function largeUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->profile_photo_large
                ? asset('storage/avatars/producers/large/'.$this->profile_photo_large)
                : $this->profile_photo_url, // fallback to original if not generated yet
        );
    }

    /**
     * Get the full URL for the agency logo.
     */
    protected function agencyLogoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->agency_logo
                ? asset('storage/logos/agencies/'.$this->agency_logo)
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
                ? asset('storage/logos/agencies/thumbnails/'.$this->agency_logo_thumbnail)
                : null,
        );
    }

    /**
     * Get the count of published missions for this producer.
     *
     * Only counts missions with MissionStatus::Published status.
     * Excludes draft, closed, and completed missions, as well as UGC missions
     * (invisible from all public surfaces since UGC 2.1 — FR5).
     */
    protected function publishedMissionsCount(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->missions()
                ->where('status', MissionStatus::Published)
                ->where('type_mission', '!=', MissionType::Ugc->value)
                ->count(),
        );
    }

    /**
     * Get the count of completed missions for this producer.
     */
    protected function completedMissionsCount(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->missions()->where('status', MissionStatus::Completed)->count(),
        );
    }

    /**
     * Get the count of in-progress missions (with confirmed/in_progress candidatures).
     *
     * Excludes UGC missions (invisible from all public surfaces since UGC 2.1 — FR5) :
     * since 2.4 a UGC acceptance lands the candidature directly in `confirmed`.
     */
    protected function inProgressMissionsCount(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->missions()
                ->where('type_mission', '!=', MissionType::Ugc->value)
                ->whereHas('candidatures', function ($query) {
                    $query->whereIn('status', [
                        CandidatureStatus::Confirmed->value,
                        CandidatureStatus::InProgress->value,
                    ]);
                })
                ->count(),
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
     * Get the booking ratings received by this Producer.
     */
    public function bookingRatingsReceived(): HasManyThrough
    {
        return $this->hasManyThrough(
            BookingRating::class,
            User::class,
            'userable_id',
            'rated_id',
            'id',
            'id',
        )->where('users.userable_type', self::class);
    }

    /**
     * Get the average rating score for this Producer.
     */
    protected function averageRating(): Attribute
    {
        return Attribute::make(
            get: function (): ?float {
                $candidatureRatings = $this->ratingsReceived()->selectRaw('COALESCE(SUM(score), 0) as score_sum, COUNT(*) as score_count')->first();
                $bookingRatings = $this->bookingRatingsReceived()->selectRaw('COALESCE(SUM(score), 0) as score_sum, COUNT(*) as score_count')->groupBy('users.userable_id')->first();

                $candidatureCount = (int) data_get($candidatureRatings, 'score_count', 0);
                $bookingCount = (int) data_get($bookingRatings, 'score_count', 0);
                $totalCount = $candidatureCount + $bookingCount;

                if ($totalCount === 0) {
                    return null;
                }

                $totalScore = (float) data_get($candidatureRatings, 'score_sum', 0.0)
                    + (float) data_get($bookingRatings, 'score_sum', 0.0);

                return $totalScore / $totalCount;
            },
        );
    }

    /**
     * Get the total count of ratings received by this Producer.
     */
    protected function ratingsCount(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->ratingsReceived()->count() + $this->bookingRatingsReceived()->count(),
        );
    }
}
