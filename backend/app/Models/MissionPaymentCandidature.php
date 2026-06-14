<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\EscrowStatus;
use App\Exceptions\MoneyColumnImmutableException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $mission_payment_id
 * @property int $candidature_id
 * @property int $face_id
 * @property int $montant_face_recoit
 * @property \App\Enums\EscrowStatus $escrow_status
 * @property \App\Enums\AttendanceStatus $attendance_status
 * @property \Carbon\Carbon|null $notified_at
 * @property-read \App\Models\MissionPayment|null $missionPayment
 * @property-read \App\Models\Candidature|null $candidature
 * @property-read \App\Models\Face|null $face
 */
class MissionPaymentCandidature extends Model
{
    protected $fillable = [
        'mission_payment_id',
        'candidature_id',
        'face_id',
        'montant_face_recoit',
        'escrow_status',
        'attendance_status',
        'locked_at',
        'released_at',
        'refunded_at',
        'notified_at',
    ];

    /**
     * Colonnes de montant immuables après création (durcissement ugc-3-5).
     *
     * @var list<string>
     */
    private const IMMUTABLE_MONEY_COLUMNS = [
        'montant_face_recoit',
    ];

    protected static function booted(): void
    {
        static::updating(function (MissionPaymentCandidature $model): void {
            if ($model->isDirty(self::IMMUTABLE_MONEY_COLUMNS)) {
                throw new MoneyColumnImmutableException(
                    $model::class.' : colonnes de montant immuables après création ('
                    .implode(', ', array_keys(array_intersect_key(
                        $model->getDirty(),
                        array_flip(self::IMMUTABLE_MONEY_COLUMNS)
                    ))).')'
                );
            }
        });
    }

    protected function casts(): array
    {
        return [
            'escrow_status' => EscrowStatus::class,
            'attendance_status' => AttendanceStatus::class,
            'notified_at' => 'datetime',
            'montant_face_recoit' => 'integer',
            'locked_at' => 'datetime',
            'released_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MissionPayment, $this>
     */
    public function missionPayment(): BelongsTo
    {
        return $this->belongsTo(MissionPayment::class);
    }

    /**
     * @return BelongsTo<Candidature, $this>
     */
    public function candidature(): BelongsTo
    {
        return $this->belongsTo(Candidature::class);
    }

    /**
     * @return BelongsTo<Face, $this>
     */
    public function face(): BelongsTo
    {
        return $this->belongsTo(Face::class);
    }
}
