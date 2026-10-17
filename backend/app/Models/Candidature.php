<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasRouteUuid;
use App\Enums\AttendanceStatus;
use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * @property string $uuid
 * @property int $id
 * @property int $face_id
 * @property int $mission_id
 * @property string|null $message_motivation
 * @property \App\Enums\CandidatureStatus $status
 * @property \Illuminate\Support\Carbon|null $accepted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read \App\Models\Face|null $face
 * @property-read \App\Models\Mission|null $mission
 * @property-read \App\Models\Conversation|null $conversation
 * @property-read \App\Models\MissionPaymentCandidature|null $paymentEntry
 * @property-read \App\Models\Shipment|null $shipment
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Deliverable> $deliverables
 */
class Candidature extends Model
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
        'mission_id',
        'message_motivation',
        'status',
        'accepted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CandidatureStatus::class,
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * Get the Face that submitted this candidature.
     */
    public function face(): BelongsTo
    {
        return $this->belongsTo(Face::class);
    }

    /**
     * Get the Mission this candidature is for.
     */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /**
     * Get the paid escrow entry (per-Face amount) for this candidature.
     *
     * Created together with the candidature acceptance inside
     * MissionPaymentService::markAsPaid(), so it is reliably present for
     * accepted/confirmed/in_progress candidatures. Read-only helper used by
     * the admin engagements view to surface montant_face_recoit.
     */
    public function paymentEntry(): HasOne
    {
        return $this->hasOne(MissionPaymentCandidature::class, 'candidature_id');
    }

    /**
     * Get the conversation associated with this candidature.
     */
    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    /**
     * Get the UGC shipment attached to this engaged candidature (tunnel étape 3).
     *
     * @return MorphOne<Shipment, $this>
     */
    public function shipment(): MorphOne
    {
        return $this->morphOne(Shipment::class, 'owner');
    }

    /**
     * Get the UGC video deliverables uploaded for this engaged candidature (tunnel étape 5).
     * Ordonné par chrono_started_at (ordre stable par kind, AC8 4.1 / defer 4.3) :
     * l'Unboxing (chrono = recu_le) précède toujours l'Avis (chrono =
     * unboxing.validated_at, postérieur). N'ajoute aucune requête (ORDER BY).
     *
     * @return MorphMany<Deliverable, $this>
     */
    public function deliverables(): MorphMany
    {
        return $this->morphMany(Deliverable::class, 'owner')->orderBy('chrono_started_at');
    }

    /** Valeur d'affichage Face d'une candidature `pending` dont la mission n'accepte plus de candidatures. */
    public const DISPLAY_STATUS_MISSION_CLOSED = 'mission_closed';

    /** Valeur d'affichage Face d'une candidature `pending` dont la mission est en attente de paiement de la sélection (lot B2). */
    public const DISPLAY_STATUS_SELECTION_PAYMENT_PENDING = 'selection_payment_pending';

    /**
     * Statut EFFECTIF à afficher à la Face (le statut stocké n'est jamais modifié :
     * une réouverture de la mission doit rester possible). Une candidature `pending`
     * dont la mission n'est plus publiée (clôturée, en validation de présences,
     * terminée) est « mission_closed ». Le dépassement de date_limite_candidature
     * n'y suffit PAS : le Producteur peut encore accepter après cette date.
     * Mission `pending_payment` : « selection_payment_pending » pour TOUTES les candidatures
     * pending (sélectionnées ou non — l'appartenance à la sélection ne doit pas fuiter).
     */
    public function displayStatus(): string
    {
        if ($this->status === CandidatureStatus::Pending && $this->mission->status === MissionStatus::PendingPayment) {
            return self::DISPLAY_STATUS_SELECTION_PAYMENT_PENDING;
        }

        if ($this->status === CandidatureStatus::Pending && $this->mission->status !== MissionStatus::Published) {
            return self::DISPLAY_STATUS_MISSION_CLOSED;
        }

        return $this->status->value;
    }

    public function displayStatusLabel(): string
    {
        return match ($this->displayStatus()) {
            self::DISPLAY_STATUS_MISSION_CLOSED => 'Mission clôturée',
            self::DISPLAY_STATUS_SELECTION_PAYMENT_PENDING => 'Sélection en cours de paiement',
            default => $this->status->label(),
        };
    }

    /**
     * Date limite de reconfirmation d'une candidature UGC acceptée
     * (accepted_at + ugc.reconfirm_window_hours) — source unique de la règle,
     * ancre du cron ugc:expire-unreconfirmed-candidatures. Null sinon.
     */
    public function reconfirmDeadline(): ?CarbonInterface
    {
        if ($this->status !== CandidatureStatus::Accepted || $this->accepted_at === null) {
            return null;
        }

        return $this->accepted_at->copy()->addHours((int) config('ugc.reconfirm_window_hours', 48));
    }

    /**
     * Absence mission contestable par la Face, depuis l'entrée d'escrow préchargée
     * (`paymentEntry`). Null hors fenêtre / hors absence.
     *
     * @return array{state: string, deadline: string|null, can_contest: bool}|null
     */
    public function attendanceDispute(): ?array
    {
        $entry = $this->paymentEntry;

        if ($entry === null || $entry->escrow_status !== EscrowStatus::Locked) {
            return null;
        }

        $deadline = $entry->contestDeadline();

        if ($entry->attendance_status === AttendanceStatus::Disputed) {
            return [
                'state' => 'contested',
                'deadline' => $deadline?->toIso8601String(),
                'can_contest' => false,
            ];
        }

        if ($entry->attendance_status === AttendanceStatus::Absent && $deadline?->isFuture()) {
            return [
                'state' => 'contestable',
                'deadline' => $deadline->toIso8601String(),
                'can_contest' => true,
            ];
        }

        return null;
    }

    /**
     * Check if this candidature has an active conversation.
     */
    public function hasConversation(): bool
    {
        return $this->conversation()->exists();
    }

    /**
     * Check if chat can be accessed for this candidature.
     *
     * Chat is unlocked when candidature is accepted or beyond.
     */
    public function canAccessChat(): bool
    {
        return $this->status->allowsChatAccess();
    }

    /**
     * Get the conversation or fail with 404.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function getConversationOrFail(): Conversation
    {
        /** @var Conversation|null $conversation */
        $conversation = $this->conversation;

        if (! $conversation) {
            abort(404, 'Aucune conversation pour cette candidature');
        }

        return $conversation;
    }

    /**
     * Scope a query to only include pending candidatures.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', CandidatureStatus::Pending);
    }

    /**
     * Scope a query to only include accepted candidatures.
     */
    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', CandidatureStatus::Accepted);
    }

    /**
     * Scope a query to only include confirmed candidatures.
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', CandidatureStatus::Confirmed);
    }

    /**
     * Scope a query to only include in-progress candidatures.
     */
    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', CandidatureStatus::InProgress);
    }

    /**
     * Scope a query to only include completed candidatures.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', CandidatureStatus::Completed);
    }

    /**
     * Scope a query to only include rejected candidatures.
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', CandidatureStatus::Rejected);
    }

    /**
     * Get the ratings associated with this candidature.
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    /**
     * Check if this candidature can be rated.
     *
     * Delegates to CandidatureStatus::allowsRatings() which returns true
     * only when status is 'completed'.
     */
    public function canBeRated(): bool
    {
        return $this->status->allowsRatings();
    }

    /**
     * Check if a user has already rated in this candidature context.
     */
    public function hasRatingFrom(User $user): bool
    {
        return $this->ratings()->where('rater_id', $user->id)->exists();
    }
}
