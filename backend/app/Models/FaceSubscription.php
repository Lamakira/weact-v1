<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasRouteUuid;
use App\Enums\FaceSubscriptionPlan;
use App\Enums\FaceSubscriptionStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property int $face_id
 * @property \App\Enums\FaceSubscriptionPlan $plan
 * @property \App\Enums\FaceSubscriptionStatus $status
 * @property \Carbon\CarbonInterface|null $starts_at
 * @property \Carbon\CarbonInterface|null $expires_at
 * @property \Carbon\CarbonInterface|null $cancelled_at
 * @property \Carbon\CarbonInterface|null $reminder_30d_sent_at
 * @property \Carbon\CarbonInterface|null $reminder_7d_sent_at
 * @property int|null $paid_amount
 * @property \Carbon\CarbonInterface|null $paid_at
 * @property string $currency
 * @property string|null $provider
 * @property string|null $provider_reference
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Face $face
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\FaceSubscriptionAudit> $audits
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
        'reminder_30d_sent_at',
        'reminder_7d_sent_at',
        'paid_amount',
        'paid_at',
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
            'reminder_30d_sent_at' => 'datetime',
            'reminder_7d_sent_at' => 'datetime',
            'paid_amount' => 'integer',
            'paid_at' => 'datetime',
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
     * Append-only audit history of admin operations on this subscription.
     * Ordered most recent first.
     */
    public function audits(): HasMany
    {
        return $this->hasMany(FaceSubscriptionAudit::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * Scope to subscriptions that are currently active and unexpired.
     *
     * Pass `$at` when several queries must share the exact same instant
     * (e.g. the admin stats endpoint, whose "expiring soon" card must stay
     * a strict subset of "active"); defaults to now() otherwise.
     */
    public function scopeActive(Builder $query, ?CarbonInterface $at = null): Builder
    {
        return $query
            ->where('status', FaceSubscriptionStatus::Active)
            ->where('expires_at', '>', $at ?? now());
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
