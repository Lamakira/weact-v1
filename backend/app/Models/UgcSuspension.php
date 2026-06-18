<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasRouteUuid;
use App\Enums\UgcSuspensionAppealStatus;
use App\Enums\UgcSuspensionReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Suspension douce UGC d'une Face (épic 5, story 5.1) — coupe l'accès UGC + gèle
 * le premium (via FaceEntitlementService::isUgcSuspended) SANS bloquer le login.
 * Une seule ligne active par Face (reactivated_at IS NULL), garantie
 * applicativement par UgcSuspensionService (lockForUpdate + garde).
 *
 * @property int $id
 * @property string $uuid
 * @property int $face_id
 * @property int|null $shipment_id
 * @property \App\Enums\UgcSuspensionReason $reason
 * @property \App\Enums\UgcSuspensionAppealStatus $appeal_status
 * @property \Illuminate\Support\Carbon $suspended_at
 * @property \Illuminate\Support\Carbon|null $reactivated_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Face|null $face
 * @property-read \App\Models\Shipment|null $shipment
 */
class UgcSuspension extends Model
{
    use HasRouteUuid;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'face_id',
        'shipment_id',
        'reason',
        'appeal_status',
        'suspended_at',
        'reactivated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => UgcSuspensionReason::class,
            'appeal_status' => UgcSuspensionAppealStatus::class,
            'suspended_at' => 'datetime',
            'reactivated_at' => 'datetime',
        ];
    }

    public function face(): BelongsTo
    {
        return $this->belongsTo(Face::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
