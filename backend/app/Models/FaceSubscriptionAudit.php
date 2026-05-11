<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasRouteUuid;
use App\Enums\FaceSubscriptionAdminAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $face_subscription_id
 * @property int|null $admin_id
 * @property \App\Enums\FaceSubscriptionAdminAction $action
 * @property string $notes
 * @property array<string, mixed>|null $previous_state
 * @property array<string, mixed> $new_state
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read \App\Models\FaceSubscription $faceSubscription
 * @property-read \App\Models\Admin|null $admin
 */
class FaceSubscriptionAudit extends Model
{
    use HasFactory;
    use HasRouteUuid;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'face_subscription_id',
        'admin_id',
        'action',
        'notes',
        'previous_state',
        'new_state',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => FaceSubscriptionAdminAction::class,
            'previous_state' => 'array',
            'new_state' => 'array',
        ];
    }

    public function faceSubscription(): BelongsTo
    {
        return $this->belongsTo(FaceSubscription::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
