<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasRouteUuid;
use App\Enums\DateChangeReason;
use App\Enums\DateChangeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Demande de changement de date d'une mission payée ou d'un booking.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $mission_id
 * @property int|null $booking_id
 * @property int $requested_by_user_id
 * @property string $requested_by_role
 * @property \Carbon\CarbonInterface $old_date
 * @property \Carbon\CarbonInterface|null $old_end_date
 * @property \Carbon\CarbonInterface $new_date
 * @property \Carbon\CarbonInterface|null $new_end_date
 * @property DateChangeReason $reason
 * @property DateChangeStatus $status
 * @property \Carbon\CarbonInterface $respond_by
 * @property \Carbon\CarbonInterface|null $reminder_sent_at
 * @property \Carbon\CarbonInterface|null $resolved_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DateChangeResponse> $responses
 */
class DateChangeRequest extends Model
{
    use HasRouteUuid;

    protected $fillable = [
        'mission_id',
        'booking_id',
        'requested_by_user_id',
        'requested_by_role',
        'old_date',
        'old_end_date',
        'new_date',
        'new_end_date',
        'reason',
        'status',
        'respond_by',
        'reminder_sent_at',
        'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_date' => 'date',
            'old_end_date' => 'date',
            'new_date' => 'date',
            'new_end_date' => 'date',
            'reason' => DateChangeReason::class,
            'status' => DateChangeStatus::class,
            'respond_by' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === DateChangeStatus::Pending;
    }

    /** @return BelongsTo<Mission, $this> */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /** @return HasMany<DateChangeResponse, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(DateChangeResponse::class);
    }
}
