<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Experience extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'face_id',
        'titre',
        'description',
        'annee',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'annee' => 'integer',
    ];

    /**
     * Get the Face that owns this experience.
     */
    public function face(): BelongsTo
    {
        return $this->belongsTo(Face::class);
    }

    /**
     * Scope to order experiences by year in descending order (newest first).
     */
    public function scopeOrderedByYear(Builder $query): Builder
    {
        return $query->orderBy('annee', 'desc');
    }
}
