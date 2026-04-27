<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\EscrowStatus;
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
    ];

    protected function casts(): array
    {
        return [
            'escrow_status' => EscrowStatus::class,
            'attendance_status' => AttendanceStatus::class,
            'montant_face_recoit' => 'integer',
            'locked_at' => 'datetime',
            'released_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function missionPayment(): BelongsTo
    {
        return $this->belongsTo(MissionPayment::class);
    }

    public function candidature(): BelongsTo
    {
        return $this->belongsTo(Candidature::class);
    }

    public function face(): BelongsTo
    {
        return $this->belongsTo(Face::class);
    }
}
