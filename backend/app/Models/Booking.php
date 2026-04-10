<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasRouteUuid;
use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;

/**
 * @property int $id
 * @property string $uuid
 * @property \App\Enums\BookingStatus $status
 * @property \Carbon\CarbonInterface|null $accepted_at
 * @property \Carbon\CarbonInterface|null $payment_reminder_sent_at
 * @property \Carbon\CarbonInterface $date_debut
 * @property \Carbon\CarbonInterface $date_fin
 * @property int $face_id
 * @property int $producer_id
 * @property int $duree_heures
 * @property string $type_contenu
 * @property string|null $message
 * @property int $tarif_base
 * @property int $montant_total_producteur
 * @property int $montant_face_recoit
 * @property string|null $cancellation_reason
 * @property string|null $custom_cancellation_reason
 * @property int|null $fedapay_transaction_id
 * @property string|null $payment_mode
 * @property string|null $payment_initiation_key
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $face
 * @property-read \App\Models\User|null $producer
 * @property-read \App\Models\BookingRating|null $raterBookingRating
 */
class Booking extends Model
{
    use HasFactory;
    use HasRouteUuid;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'face_id',
        'producer_id',
        'status',
        'accepted_at',
        'payment_reminder_sent_at',
        'date_debut',
        'date_fin',
        'duree_heures',
        'type_contenu',
        'message',
        'tarif_base',
        'montant_total_producteur',
        'montant_face_recoit',
        'cancellation_reason',
        'custom_cancellation_reason',
        'fedapay_transaction_id',
        'payment_mode',
        'payment_initiation_key',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'accepted_at' => 'datetime',
            'payment_reminder_sent_at' => 'datetime',
            'date_debut' => 'datetime',
            'date_fin' => 'datetime',
            'duree_heures' => 'integer',
            'tarif_base' => 'integer',
            'montant_total_producteur' => 'integer',
            'montant_face_recoit' => 'integer',
        ];
    }

    /**
     * Get the Face (user) for this booking.
     */
    public function face(): BelongsTo
    {
        return $this->belongsTo(User::class, 'face_id');
    }

    /**
     * Get the Producer (user) for this booking.
     */
    public function producer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'producer_id');
    }

    /**
     * Get the escrow transaction for this booking.
     */
    public function escrowTransaction(): HasOne
    {
        return $this->hasOne(EscrowTransaction::class);
    }

    /**
     * Get the chat messages for this booking.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(BookingMessage::class)->orderBy('created_at');
    }

    /**
     * Get all ratings for this booking.
     */
    public function bookingRatings(): HasMany
    {
        return $this->hasMany(BookingRating::class);
    }

    /**
     * Get one rating for this booking constrained to the authenticated rater.
     *
     * IMPORTANT: This relation has no built-in WHERE clause. It MUST be loaded
     * via an eager-load closure that constrains by rater_id, e.g.:
     *   ->load(['raterBookingRating' => fn($q) => $q->where('rater_id', auth()->id())])
     * Accessing it without that closure returns the first BookingRating found,
     * regardless of who the rater is.
     */
    public function raterBookingRating(): HasOne
    {
        $relation = $this->hasOne(BookingRating::class);
        $userId = Auth::id();

        if ($userId === null) {
            return $relation->whereRaw('1 = 0');
        }

        return $relation->where('rater_id', $userId);
    }
}
