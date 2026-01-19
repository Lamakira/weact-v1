<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MissionGender;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mission extends Model
{
    use HasFactory;

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
        'profil_recherche',
        'budget',
        'date_limite_candidature',
        'nombre_faces_voulu',
        'type_mission',
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
}
