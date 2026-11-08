<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jour entier (fuseau métier) que la Face a marqué indisponible dans son calendrier.
 *
 * @property int $id
 * @property int $face_id faces.id (pas users.id)
 * @property \Illuminate\Support\Carbon $day
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class FaceUnavailableDay extends Model
{
    /** @var list<string> */
    protected $fillable = ['face_id', 'day'];

    protected function casts(): array
    {
        return [
            'day' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<Face, $this>
     */
    public function face(): BelongsTo
    {
        return $this->belongsTo(Face::class);
    }
}
