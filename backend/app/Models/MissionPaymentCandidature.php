<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EscrowStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionPaymentCandidature extends Model
{
    protected $fillable = [
        'mission_payment_id',
        'candidature_id',
        'face_id',
        'montant_face_recoit',
        'escrow_status',
        'locked_at',
        'released_at',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'escrow_status' => EscrowStatus::class,
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
