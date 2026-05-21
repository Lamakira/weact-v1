<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasRouteUuid;
use App\Enums\FaceCategory;
use App\Enums\FaceGender;
use App\Enums\FaceNiche;
use App\Enums\FaceSubscriptionStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * @property string $uuid
 * @property string|null $username
 * @property string|null $nom
 * @property string|null $prenom
 * @property string|null $nationalite
 * @property array<int, string>|null $langues
 * @property string|null $bio
 * @property string|null $ville
 * @property string|null $pays
 * @property string|null $whatsapp_number
 * @property int|null $taille
 * @property int|null $poids
 * @property int|null $tarif_horaire
 * @property int|null $tarif_journalier
 * @property bool $show_age
 * @property bool $is_featured
 * @property string|null $presentation_video
 * @property string|null $acting_video
 * @property \App\Enums\FaceGender|null $sexe
 * @property \Carbon\CarbonInterface|null $date_naissance
 * @property bool $is_available
 * @property-read int|null $age
 * @property-read string $display_name
 * @property-read string|null $profile_photo_url
 * @property-read string|null $thumbnail_url
 * @property-read string|null $medium_url
 * @property-read string|null $presentation_video_url
 * @property-read string|null $presentation_video_thumbnail_url
 * @property-read string|null $acting_video_url
 * @property-read string|null $acting_video_thumbnail_url
 * @property-read string|null $formatted_location
 * @property-read string|null $formatted_tarif_horaire
 * @property-read string|null $formatted_tarif_journalier
 * @property-read string $availability_badge
 * @property-read string $availability_badge_color
 * @property-read array<int, array{key: string, label: string}> $profile_completion_missing
 * @property-read bool $profile_completion_is_complete
 * @property-read float|null $average_rating
 * @property-read int $ratings_count
 * @property-read int $profile_completion_percentage
 * @property-read User|null $user
 * @property int|null $experiences_count
 * @property int|null $photos_count
 * @property-read \Illuminate\Support\Carbon|null $created_at
 * @property-read \Illuminate\Support\Carbon|null $updated_at
 */
class Face extends Model
{
    use HasFactory, HasRouteUuid;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nom',
        'prenom',
        'username',
        'sexe',
        'date_naissance',
        'nationalite',
        'langues',
        'profile_photo',
        'profile_photo_thumbnail',
        'profile_photo_medium',
        'presentation_video',
        'presentation_video_thumbnail',
        'acting_video',
        'acting_video_thumbnail',
        'bio',
        'ville',
        'pays',
        'whatsapp_number',
        'taille',
        'poids',
        'categories',
        'niches',
        'tarif_horaire',
        'tarif_journalier',
        'is_available',
        'is_featured',
        'show_age',
        'rating_penalty',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'sexe' => FaceGender::class,
        'date_naissance' => 'date',
        'langues' => 'array',
        'categories' => 'array',
        'niches' => 'array',
        'is_available' => 'boolean',
        'is_featured' => 'boolean',
        'show_age' => 'boolean',
        'rating_penalty' => 'float',
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
        'display_name',
        'presentation_video_url',
        'presentation_video_thumbnail_url',
        'acting_video_url',
        'acting_video_thumbnail_url',
        'formatted_location',
        'formatted_tarif_horaire',
        'formatted_tarif_journalier',
        'availability_badge',
        'availability_badge_color',
        'profile_completion_percentage',
        'profile_completion_missing',
        'profile_completion_is_complete',
        'average_rating',
        'ratings_count',
    ];

    /**
     * Get the user associated with this Face.
     */
    public function user(): MorphOne
    {
        return $this->morphOne(User::class, 'userable');
    }

    /**
     * Get the display name for this Face.
     * Returns "prenom nom" or username if neither are set.
     */
    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $fullName = trim("{$this->prenom} {$this->nom}");

                return $fullName !== '' ? $fullName : ($this->username ?? 'Face');
            },
        );
    }

    /**
     * Get the age calculated from date_naissance.
     */
    protected function age(): Attribute
    {
        return Attribute::make(
            get: fn (): ?int => $this->date_naissance
                ? Carbon::parse($this->date_naissance)->age
                : null,
        );
    }

    /**
     * Get the full URL for the profile photo.
     */
    protected function profilePhotoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->profile_photo
                ? asset('storage/avatars/faces/'.$this->profile_photo)
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
                ? asset('storage/avatars/faces/thumbnails/'.$this->profile_photo_thumbnail)
                : null,
        );
    }

    /**
     * Get the full URL for the medium profile photo (800px wide WebP).
     */
    protected function mediumUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->profile_photo_medium
                ? asset('storage/avatars/faces/medium/'.$this->profile_photo_medium)
                : $this->profile_photo_url, // fallback to original if not generated yet
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
     * Get all subscriptions (current + historical) attached to this Face.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(FaceSubscription::class);
    }

    /**
     * The currently active, unexpired subscription (if any).
     *
     * Useful for eager-loading the single relevant row that drives entitlements:
     *   Face::with('activeSubscription')->find($id)
     * `FaceEntitlementService` can read this preloaded relation when available,
     * but should fall back to its own query when the relation has not been loaded.
     *
     * Resolution policy when multiple active subscription rows overlap:
     * longest `expires_at` wins; ties are broken by highest `id` (most recently
     * inserted row).
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(FaceSubscription::class)
            ->ofMany(
                [
                    'expires_at' => 'max',
                    'id' => 'max',
                ],
                fn ($query) => $query
                    ->where('status', FaceSubscriptionStatus::Active)
                    ->where('expires_at', '>', now())
            );
    }

    /**
     * Get the professional experiences for this Face.
     */
    public function experiences(): HasMany
    {
        return $this->hasMany(Experience::class);
    }

    /**
     * Get the candidatures submitted by this Face.
     */
    public function candidatures(): HasMany
    {
        return $this->hasMany(Candidature::class);
    }

    /**
     * Get the full URL for the presentation video.
     */
    protected function presentationVideoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->presentation_video
                ? asset('storage/videos/faces/presentation/'.$this->presentation_video)
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
                ? asset('storage/videos/faces/presentation/thumbnails/'.$this->presentation_video_thumbnail)
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
                ? asset('storage/videos/faces/acting/'.$this->acting_video)
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
                ? asset('storage/videos/faces/acting/thumbnails/'.$this->acting_video_thumbnail)
                : null,
        );
    }

    /**
     * Get the formatted location string (Ville, Pays).
     */
    protected function formattedLocation(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                $parts = array_filter([
                    $this->ville,
                    $this->pays,
                ]);

                return count($parts) > 0 ? implode(', ', $parts) : null;
            },
        );
    }

    /**
     * Get the formatted half-day rate (e.g., "75 000 XOF/demi-journée").
     */
    protected function formattedTarifHoraire(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->tarif_horaire !== null
                ? number_format($this->tarif_horaire, 0, ',', ' ').' XOF/demi-journée'
                : null,
        );
    }

    /**
     * Get the formatted daily rate (e.g., "250 000 XOF/jour").
     */
    protected function formattedTarifJournalier(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->tarif_journalier !== null
                ? number_format($this->tarif_journalier, 0, ',', ' ').' XOF/jour'
                : null,
        );
    }

    /**
     * Get the availability badge text ("Disponible" or "Indisponible").
     */
    protected function availabilityBadge(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->is_available ? 'Disponible' : 'Indisponible',
        );
    }

    /**
     * Get the availability badge color ("green" or "grey").
     */
    protected function availabilityBadgeColor(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->is_available ? 'green' : 'grey',
        );
    }

    /**
     * Get the profile completion percentage (0-100).
     *
     * Required fields (8 total):
     * - profile_photo
     * - presentation_video
     * - acting_video
     * - bio
     * - ville
     * - categorie
     * - tarif_horaire OR tarif_journalier (at least one)
     * - langues
     * - whatsapp_number
     */
    protected function profileCompletionPercentage(): Attribute
    {
        return Attribute::make(
            get: function (): int {
                $completed = 0;
                $total = 9;

                if ($this->profile_photo) {
                    $completed++;
                }
                if ($this->presentation_video) {
                    $completed++;
                }
                if ($this->acting_video) {
                    $completed++;
                }
                if ($this->bio) {
                    $completed++;
                }
                if ($this->ville) {
                    $completed++;
                }
                if (! empty($this->categories)) {
                    $completed++;
                }
                if ($this->tarif_horaire !== null || $this->tarif_journalier !== null) {
                    $completed++;
                }
                if (! empty($this->langues)) {
                    $completed++;
                }
                if ($this->whatsapp_number) {
                    $completed++;
                }

                return (int) round(($completed / $total) * 100);
            },
        );
    }

    protected function profileCompletionMissing(): Attribute
    {
        return Attribute::make(
            get: function (): array {
                $missing = [];

                if (! $this->profile_photo) {
                    $missing[] = ['key' => 'profile_photo', 'label' => 'Ajoutez une photo de profil'];
                }
                if (! $this->presentation_video) {
                    $missing[] = ['key' => 'presentation_video', 'label' => 'Ajoutez une vidéo de présentation'];
                }
                if (! $this->acting_video) {
                    $missing[] = ['key' => 'acting_video', 'label' => "Ajoutez une vidéo d'acting"];
                }
                if (! $this->bio) {
                    $missing[] = ['key' => 'bio', 'label' => 'Ajoutez une bio'];
                }
                if (! $this->ville) {
                    $missing[] = ['key' => 'ville', 'label' => 'Ajoutez votre ville'];
                }
                if (empty($this->categories)) {
                    $missing[] = ['key' => 'categories', 'label' => 'Sélectionnez votre catégorie'];
                }
                if ($this->tarif_horaire === null && $this->tarif_journalier === null) {
                    $missing[] = ['key' => 'tarifs', 'label' => 'Ajoutez vos tarifs'];
                }
                if (empty($this->langues)) {
                    $missing[] = ['key' => 'langues', 'label' => 'Ajoutez vos langues parlées'];
                }
                if (! $this->whatsapp_number) {
                    $missing[] = ['key' => 'whatsapp_number', 'label' => 'Ajoutez votre numéro WhatsApp'];
                }

                return $missing;
            },
        );
    }

    /**
     * Check if the profile is complete (100%).
     */
    protected function profileCompletionIsComplete(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->profile_completion_percentage === 100,
        );
    }

    /**
     * Resolve stored category string values to {value, label} pairs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function categoriesWithLabels(): array
    {
        $categories = $this->categories ?? [];

        return array_values(array_filter(array_map(function (string $value): ?array {
            $enum = FaceCategory::tryFrom($value);

            return $enum ? ['value' => $enum->value, 'label' => $enum->label()] : null;
        }, $categories)));
    }

    /**
     * Resolve stored niche string values to {value, label} pairs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function nichesWithLabels(): array
    {
        $niches = $this->niches ?? [];

        return array_values(array_filter(array_map(function (string $value): ?array {
            $enum = FaceNiche::tryFrom($value);

            return $enum ? ['value' => $enum->value, 'label' => $enum->label()] : null;
        }, $niches)));
    }

    /**
     * Get the ratings received by this Face.
     */
    public function ratingsReceived(): MorphMany
    {
        return $this->morphMany(Rating::class, 'rated');
    }

    /**
     * Get the booking ratings received by this Face.
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
     * Get the average rating score for this Face.
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

                $avg = $totalScore / $totalCount;
                $penalized = $avg - (float) ($this->rating_penalty ?? 0.0);

                return max(1.0, $penalized);
            },
        );
    }

    /**
     * Get the total count of ratings received by this Face.
     */
    protected function ratingsCount(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->ratingsReceived()->count() + $this->bookingRatingsReceived()->count(),
        );
    }
}
