<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasRouteUuid;
use App\Enums\FaceSubscriptionPlan;
use App\Enums\FaceSubscriptionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $face_id
 * @property \App\Enums\FaceSubscriptionPlan $plan
 * @property \App\Enums\FaceSubscriptionStatus $status
 * @property \Carbon\CarbonInterface|null $starts_at
 * @property \Carbon\CarbonInterface|null $expires_at
 * @property \Carbon\CarbonInterface|null $cancelled_at
 * @property int|null $paid_amount
 * @property string $currency
 * @property string|null $provider
 * @property string|null $provider_reference
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Face $face
 */
class FaceSubscription extends Model
{
    use HasFactory;
    use HasRouteUuid;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'face_id',
        'plan',
        'status',
        'starts_at',
        'expires_at',
        'cancelled_at',
        'paid_amount',
        'currency',
        'provider',
        'provider_reference',
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
            'plan' => FaceSubscriptionPlan::class,
            'status' => FaceSubscriptionStatus::class,
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'paid_amount' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * The Face this subscription belongs to.
     */
    public function face(): BelongsTo
    {
        return $this->belongsTo(Face::class);
    }

    /**
     * Scope to subscriptions that are currently active and unexpired.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', FaceSubscriptionStatus::Active)
            ->where('expires_at', '>', now());
    }

    /**
     * Whether this subscription is currently active and unexpired.
     */
    public function isActive(): bool
    {
        return $this->status === FaceSubscriptionStatus::Active
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }
}
