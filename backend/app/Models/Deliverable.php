<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasRouteUuid;
use App\Enums\DeliverableKind;
use App\Enums\DeliverableValidationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Livrable vidéo d'un deal UGC (tunnel étape 5, FR6/FR7) — owner polymorphe :
 * Booking (deal direct) | Candidature (Face engagée sur mission UGC). Le tunnel
 * porte 2 livrables canoniques fixes : unboxing (7 j) + avis (14 j). La ligne
 * naît À L'UPLOAD en in_review (D-4.1.b), jamais à la réception ; 4.1 n'écrit
 * que l'unboxing. La validation Producteur (validated/rejected/retouche,
 * validated_at, review_note) et l'avis arrivent en 4.3.
 *
 * @property int $id
 * @property string $uuid
 * @property string $owner_type
 * @property int $owner_id
 * @property \App\Enums\DeliverableKind $kind
 * @property \App\Enums\DeliverableValidationStatus $validation_status
 * @property \Illuminate\Support\Carbon $chrono_started_at
 * @property \Illuminate\Support\Carbon $deadline_at
 * @property string $video_path
 * @property string|null $thumbnail_path
 * @property int|null $duree_seconds
 * @property string|null $review_note
 * @property \Illuminate\Support\Carbon|null $validated_at
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Booking|Candidature|null $owner
 */
class Deliverable extends Model
{
    use HasFactory;
    use HasRouteUuid;

    /**
     * Clés morph volontairement HORS fillable : posées par la relation
     * $owner->deliverables()->create(), jamais mass-assignées depuis l'input
     * (calque Shipment).
     *
     * @var list<string>
     */
    protected $fillable = [
        'kind',
        'validation_status',
        'chrono_started_at',
        'deadline_at',
        'video_path',
        'thumbnail_path',
        'duree_seconds',
        'review_note',
        'validated_at',
        'submitted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => DeliverableKind::class,
            'validation_status' => DeliverableValidationStatus::class,
            'chrono_started_at' => 'datetime',
            'deadline_at' => 'datetime',
            'duree_seconds' => 'integer',
            'validated_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
