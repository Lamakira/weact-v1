<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasRouteUuid;
use App\Enums\AttendanceStatus;
use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Enums\UgcTunnelStatus;
use App\Services\MissionPaymentService;
use App\Support\BusinessTimeSql;
use Carbon\Carbon;
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
 * @property \Illuminate\Support\Carbon|null $confirmation_reminder_sent_at
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
        'confirmation_reminder_sent_at',
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
            'confirmation_reminder_sent_at' => 'datetime',
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
        // Lot B6 : expirée sans avoir jamais été retenue = le Producer n'a pas répondu à temps
        // (une place retenue puis non confirmée, lot B3, porte un accepted_at).
        if ($this->status === CandidatureStatus::Expired && $this->accepted_at === null) {
            return 'Sans réponse du producteur';
        }

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
     * Échéance de confirmation d'une Face sélectionnée sur une mission cash (lot B3) :
     * min(accepted_at + 48 h, début du jour de tournage dans le fuseau métier).
     * Source unique de la règle (crons de relance/expiration, API, emails). Null hors
     * mission standard, hors statut Accepted ou sans ancrage `accepted_at`.
     */
    public function confirmationDeadline(): ?CarbonInterface
    {
        if ($this->status !== CandidatureStatus::Accepted || $this->accepted_at === null) {
            return null;
        }

        $mission = $this->mission;

        if ($mission === null || $mission->type_mission === MissionType::Ugc) {
            return null;
        }

        $deadline = $this->accepted_at->copy()->addHours(MissionPaymentService::FACE_CONFIRMATION_WINDOW_HOURS);

        if ($mission->date_tournage !== null) {
            $shootingStart = Carbon::parse(
                $mission->date_tournage->toDateString(),
                (string) config('app.business_timezone'),
            )->startOfDay();

            if ($shootingStart->lessThan($deadline)) {
                $deadline = $shootingStart;
            }
        }

        // Plancher : une sélection payée la veille ou le jour du tournage laisse quand même
        // à la Face un délai réel pour confirmer (sinon expiration/remboursement immédiats).
        $floor = $this->accepted_at->copy()->addHours(MissionPaymentService::FACE_CONFIRMATION_MIN_HOURS);

        return $deadline->lessThan($floor) ? $floor : $deadline;
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
     * Filtre « À traiter » de « Mes candidatures » (lot D7) : candidatures où la Face doit agir
     * maintenant, c'est-à-dire sélectionnées (Accepted) avec un délai encore ouvert :
     *  - reconfirmation UGC : accepted_at + ugc.reconfirm_window_hours (reconfirmDeadline) ;
     *  - participation à confirmer, mission standard : confirmationDeadline() = max(min(accepted_at + 48 h,
     *    début du tournage) ;
     *  - vidéo UGC à envoyer (scopeAwaitingFaceVideo), candidature déjà confirmée.
     *
     * @param  Builder<Candidature>  $query
     */
    public function scopeToProcess(Builder $query): Builder
    {
        return $query->where(fn (Builder $root) => $root
            ->where(fn (Builder $b) => $b->toProcessSelection())
            ->orWhere(fn (Builder $b) => $b->awaitingFaceVideo()));
    }

    /**
     * Sélection acceptée dont le délai de (re)confirmation est encore ouvert (voir scopeToProcess).
     *
     * @param  Builder<Candidature>  $query
     */
    public function scopeToProcessSelection(Builder $query): Builder
    {
        $now = now();
        $ugc = MissionType::Ugc->value;

        return $query->where('status', CandidatureStatus::Accepted->value)
            ->whereNotNull('accepted_at')
            ->where(function (Builder $cases) use ($now, $ugc): void {
                $cases
                    ->orWhere(fn (Builder $b) => $b
                        ->where('accepted_at', '>', $now->copy()->subHours((int) config('ugc.reconfirm_window_hours', 48)))
                        ->whereHas('mission', fn (Builder $m) => $m->where('type_mission', $ugc)))
                    // Plancher : toujours au moins 12 h pour confirmer, même si le tournage est imminent.
                    ->orWhere(fn (Builder $b) => $b
                        ->where('accepted_at', '>', $now->copy()->subHours(MissionPaymentService::FACE_CONFIRMATION_MIN_HOURS))
                        ->whereHas('mission', fn (Builder $m) => $m->where('type_mission', '!=', $ugc)))
                    // Sinon 48 h, tant que le jour de tournage n'est pas atteint.
                    ->orWhere(fn (Builder $b) => $b
                        ->where('accepted_at', '>', $now->copy()->subHours(MissionPaymentService::FACE_CONFIRMATION_WINDOW_HOURS))
                        ->whereHas('mission', fn (Builder $m) => $m
                            ->where('type_mission', '!=', $ugc)
                            ->where(fn (Builder $d) => $d
                                ->whereNull('date_tournage')
                                ->orWhereRaw(BusinessTimeSql::localInstant('date_tournage').' > ?', [$now]))));
            });
    }

    /**
     * Candidatures UGC engagées (Confirmed) où la Face a une vidéo à envoyer : même règle de tunnel que
     * Booking::awaitingFaceVideo (Unboxing après réception, Avis attendu). Source du compteur « livrables » et du filtre « À traiter ».
     *
     * @param  Builder<Candidature>  $query
     */
    public function scopeAwaitingFaceVideo(Builder $query): Builder
    {
        return $query->where('status', CandidatureStatus::Confirmed->value)
            // Garde de remboursement de UgcDeliverableService::guardCandidature : pas d'upload pendant / après un remboursement.
            ->whereHas('mission', fn (Builder $m) => $m->where('type_mission', MissionType::Ugc->value)
                ->whereNull('commission_refund_requested_at')
                ->whereNull('commission_refunded_at'))
            ->whereHas('shipment', fn (Builder $s) => $s->whereIn('tunnel_status', [
                UgcTunnelStatus::Received->value,
                UgcTunnelStatus::AvisPending->value,
            ]));
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
     *
     * @return HasMany<Rating, $this>
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
     * Notation de la mission vue par la Face (lot C8) : `can_rate` + `my_rating` du rater donné.
     *
     * Lit uniquement la relation `ratings` déjà chargée (jamais de lazy-load) : l'appelant la précharge,
     * idéalement contrainte au rater, pour rester sans N+1 en liste.
     *
     * @return array{can_rate: bool, my_rating: array{score: int, comment: ?string, created_at: ?string}|null}
     */
    public function raterRatingPayload(?int $raterId): array
    {
        $rating = $raterId === null
            ? null
            : $this->ratings->first(fn (Rating $r): bool => $r->rater_id === $raterId);

        return [
            'can_rate' => $rating === null && $this->canBeRated(),
            'my_rating' => $rating === null ? null : [
                'score' => (int) $rating->score,
                'comment' => $rating->comment,
                'created_at' => $rating->created_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * Check if a user has already rated in this candidature context.
     */
    public function hasRatingFrom(User $user): bool
    {
        return $this->ratings()->where('rater_id', $user->id)->exists();
    }
}
