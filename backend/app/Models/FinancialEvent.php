<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FinancialEventType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialEvent extends Model
{
    use HasFactory;

    /**
     * Prevent update and delete operations — FinancialEvent is an immutable audit trail.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('FinancialEvent records are immutable and cannot be updated.');
        });

        static::deleting(function () {
            throw new \RuntimeException('FinancialEvent records are immutable and cannot be deleted.');
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'booking_id',
        'amount',
        'fedapay_ref',
        'idempotency_key',
        'status',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FinancialEventType::class,
            'metadata' => 'array',
            'amount' => 'integer',
        ];
    }

    /**
     * Get the booking for this financial event.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
