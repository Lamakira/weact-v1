<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DateChangeResponseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Réponse d'une Face (candidature confirmée ou en cours) à un changement de date de mission.
 *
 * @property int $id
 * @property int $date_change_request_id
 * @property int $candidature_id
 * @property DateChangeResponseStatus $status
 * @property \Carbon\CarbonInterface|null $responded_at
 */
class DateChangeResponse extends Model
{
    protected $fillable = [
        'date_change_request_id',
        'candidature_id',
        'status',
        'responded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DateChangeResponseStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<DateChangeRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(DateChangeRequest::class, 'date_change_request_id');
    }

    /** @return BelongsTo<Candidature, $this> */
    public function candidature(): BelongsTo
    {
        return $this->belongsTo(Candidature::class);
    }
}
