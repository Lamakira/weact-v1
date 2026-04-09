<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Concerns\HasRouteUuid;

class Experience extends Model
{
    use HasFactory;
    use HasRouteUuid;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'face_id',
        'titre',
        'description',
        'date_debut',
        'date_fin',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
    ];

    /**
     * Get the Face that owns this experience.
     */
    public function face(): BelongsTo
    {
        return $this->belongsTo(Face::class);
    }

    /**
     * Scope to order experiences by date in descending order (most recent first).
     */
    public function scopeOrderedByDate(Builder $query): Builder
    {
        return $query->orderBy('date_debut', 'desc');
    }

    /**
     * Check if the experience is ongoing (no end date).
     */
    protected function isOngoing(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->date_fin === null,
        );
    }

    /**
     * Get the formatted date period string.
     * Format: "dd/mm/yyyy - dd/mm/yyyy" or "dd/mm/yyyy - Présent"
     */
    protected function formattedPeriod(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                /** @var Carbon $dateDebut */
                $dateDebut = $this->date_debut;
                $startFormatted = $dateDebut->format('d/m/Y');

                if ($this->date_fin === null) {
                    return "{$startFormatted} - Présent";
                }

                /** @var Carbon $dateFin */
                $dateFin = $this->date_fin;

                return "{$startFormatted} - {$dateFin->format('d/m/Y')}";
            },
        );
    }
}
