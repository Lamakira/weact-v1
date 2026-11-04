<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasRouteUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Autorisation d'utilisation de l'image entre une Face et un Producteur (un contrat par booking).
 * WeAct archive ; les user agents ne sont jamais exposés.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $booking_id
 * @property string $template_version
 * @property string $finalite
 * @property list<string> $supports
 * @property string $territoire
 * @property int $duree_annees
 * @property array<string, mixed> $snapshot
 * @property string $producer_signed_name
 * @property \Illuminate\Support\Carbon $producer_signed_at
 * @property string $producer_signed_email_masked
 * @property string|null $face_signed_name
 * @property \Illuminate\Support\Carbon|null $face_signed_at
 * @property string|null $face_signed_email_masked
 * @property string|null $producer_user_agent
 * @property string|null $face_user_agent
 * @property string|null $guardian_name
 * @property string|null $guardian_relation
 * @property string|null $pdf_path
 * @property string|null $pdf_sha256
 * @property \Illuminate\Support\Carbon|null $effective_at
 * @property \Illuminate\Support\Carbon|null $voided_at
 * @property-read Booking|null $booking
 */
class ImageContract extends Model
{
    use HasRouteUuid;

    public const STATUS_AWAITING_FACE = 'awaiting_face';

    public const STATUS_SIGNED = 'signed';

    public const STATUS_EFFECTIVE = 'effective';

    public const STATUS_VOID = 'void';

    /** @var list<string> */
    protected $fillable = [
        'booking_id',
        'template_version',
        'finalite',
        'supports',
        'territoire',
        'duree_annees',
        'snapshot',
        'producer_signed_name',
        'producer_signed_at',
        'producer_signed_email_masked',
        'face_signed_name',
        'face_signed_at',
        'face_signed_email_masked',
        'producer_user_agent',
        'face_user_agent',
        'guardian_name',
        'guardian_relation',
        'pdf_path',
        'pdf_sha256',
        'effective_at',
        'voided_at',
    ];

    /** @var list<string> */
    protected $hidden = ['producer_user_agent', 'face_user_agent'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'supports' => 'array',
            'snapshot' => 'array',
            'duree_annees' => 'integer',
            'producer_signed_at' => 'datetime',
            'face_signed_at' => 'datetime',
            'effective_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function isSignedByFace(): bool
    {
        return $this->face_signed_at !== null;
    }

    /** Une prise d'effet acquise ne repasse jamais à « annulé ». */
    public function status(): string
    {
        if ($this->effective_at !== null) {
            return self::STATUS_EFFECTIVE;
        }

        if ($this->voided_at !== null) {
            return self::STATUS_VOID;
        }

        return $this->isSignedByFace() ? self::STATUS_SIGNED : self::STATUS_AWAITING_FACE;
    }

    /** Référence lisible portée par le PDF et les e-mails. */
    public function reference(): string
    {
        return 'WEACT-IMG-'.strtoupper(substr(str_replace('-', '', $this->uuid), 0, 8));
    }
}
