<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasRouteUuid;
use App\Enums\MissionGender;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * @property string $uuid
 * @property string $slug
 * @property string $titre
 * @property string $description
 * @property \Carbon\CarbonInterface|null $date_tournage
 * @property \Carbon\CarbonInterface|null $shooting_reminder_sent_at
 * @property \Carbon\CarbonInterface|null $date_limite_candidature
 * @property string|null $profil_recherche
 * @property int $budget
 * @property int $nombre_faces_voulu
 * @property string|null $type_mission_autre
 * @property string|null $lieu
 * @property string|null $duree
 * @property \App\Enums\MissionStatus $status
 * @property \App\Enums\MissionType|null $type_mission
 * @property \App\Enums\MissionGender|null $genre_voulu
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $candidatures_count
 * @property-read \App\Models\Producer|null $producer
 * @property-read \App\Models\MissionPayment|null $payment
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Candidature> $candidatures
 */
class Mission extends Model
{
    use HasFactory;
    use HasRouteUuid;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Mission $mission): void {
            if (empty($mission->slug)) {
                $mission->slug = self::generateUniqueSlug($mission->titre);
            }
        });

        static::updating(function (Mission $mission): void {
            if (
                $mission->isDirty('titre')
                && (
                    $mission->status === MissionStatus::Draft
                    || $mission->getOriginal('status') === MissionStatus::Draft->value
                )
            ) {
                $mission->slug = self::generateUniqueSlug($mission->titre, $mission->id);
            }
        });
    }

    /**
     * Generate a unique slug from the given title.
     */
    public static function generateUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $slug = Str::slug($title);

        if ($slug === '') {
            $slug = 'mission';
        }

        $original = $slug;
        $counter = 2;

        while (static::query()
            ->where('slug', $slug)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists()
        ) {
            $slug = "{$original}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'producer_id',
        'titre',
        'description',
        'date_tournage',
        'shooting_reminder_sent_at',
        'profil_recherche',
        'budget',
        'date_limite_candidature',
        'nombre_faces_voulu',
        'type_mission',
        'type_mission_autre',
        'genre_voulu',
        'lieu',
        'duree',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_tournage' => 'date',
            'shooting_reminder_sent_at' => 'datetime',
            'date_limite_candidature' => 'date',
            'budget' => 'integer',
            'nombre_faces_voulu' => 'integer',
            'status' => MissionStatus::class,
            'type_mission' => MissionType::class,
            'genre_voulu' => MissionGender::class,
        ];
    }

    /**
     * Get the producer that owns this mission.
     */
    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }

    /**
     * Get the candidatures for this mission.
     */
    public function candidatures(): HasMany
    {
        return $this->hasMany(Candidature::class);
    }

    /**
     * Get the payment record for this mission.
     */
    public function payment(): HasOne
    {
        return $this->hasOne(MissionPayment::class);
    }

    /**
     * Scope a query to only include missions with a specific status.
     */
    public function scopeStatus(Builder $query, MissionStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include draft missions.
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', MissionStatus::Draft);
    }

    /**
     * Scope a query to only include published missions.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', MissionStatus::Published);
    }

    /**
     * Scope a query to only include missions pending payment.
     */
    public function scopePendingPayment(Builder $query): Builder
    {
        return $query->where('status', MissionStatus::PendingPayment);
    }

    /**
     * Scope a query to only include closed missions.
     */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', MissionStatus::Closed);
    }

    /**
     * Scope a query to only include completed missions.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', MissionStatus::Completed);
    }

    /**
     * Scope a query to only include missions accepting candidatures.
     */
    public function scopeAcceptingCandidatures(Builder $query): Builder
    {
        return $query->where('status', MissionStatus::Published)
            ->where('date_limite_candidature', '>=', now()->toDateString());
    }

    /**
     * Check if the mission is currently accepting candidatures.
     */
    public function isAcceptingCandidatures(): bool
    {
        return $this->status === MissionStatus::Published
            && $this->date_limite_candidature >= now()->toDateString();
    }

    /**
     * Check if this mission has a pending (unpaid) payment record.
     */
    public function hasPendingPayment(): bool
    {
        return $this->status === MissionStatus::PendingPayment;
    }
}
