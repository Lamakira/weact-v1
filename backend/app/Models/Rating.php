<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use InvalidArgumentException;

/**
 * @property int $id
 * @property int $candidature_id
 * @property int $rater_id
 * @property int $rated_id
 * @property string $rated_type
 * @property int $score
 * @property string|null $comment
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property User $rater
 * @property Face|Producer $rated
 */
class Rating extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'candidature_id',
        'rater_id',
        'rated_id',
        'rated_type',
        'score',
        'comment',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saving(function (Rating $rating) {
            if ($rating->score < 1 || $rating->score > 5) {
                throw new InvalidArgumentException('Score must be between 1 and 5');
            }
        });
    }

    /**
     * Get the candidature this rating belongs to.
     */
    public function candidature(): BelongsTo
    {
        return $this->belongsTo(Candidature::class);
    }

    /**
     * Get the user who gave this rating.
     */
    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_id');
    }

    /**
     * Get the entity being rated (Face or Producer).
     */
    public function rated(): MorphTo
    {
        return $this->morphTo();
    }
}
