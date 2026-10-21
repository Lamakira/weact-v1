<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasRouteUuid;
use App\Enums\AttendanceStatus;
use App\Enums\CandidatureStatus;
use App\Enums\CompensationType;
use App\Enums\DeliverableValidationStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionDisplayStatus;
use App\Enums\MissionGender;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Enums\UgcRefundReason;
use App\Services\MissionPaymentService;
use App\Support\BusinessTimeSql;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $uuid
 * @property string $slug
 * @property string $titre
 * @property string $description
 * @property \Carbon\CarbonInterface|null $date_tournage
 * @property \Carbon\CarbonInterface|null $shooting_reminder_sent_at
 * @property \Carbon\CarbonInterface|null $date_limite_candidature
 * @property \Carbon\CarbonInterface|null $candidature_response_reminder_sent_at
 * @property \Carbon\CarbonInterface|null $candidature_digest_sent_at
 * @property string|null $profil_recherche
 * @property int $budget
 * @property int $nombre_faces_voulu
 * @property string|null $type_mission_autre
 * @property string|null $lieu
 * @property string|null $duree
 * @property \App\Enums\MissionStatus $status
 * @property \App\Enums\MissionType|null $type_mission
 * @property \App\Enums\MissionGender|null $genre_voulu
 * @property \App\Enums\CompensationType|null $type_compensation
 * @property string|null $nom_produit
 * @property int|null $valeur_produit
 * @property int|null $nombre_videos
 * @property int|null $montant_remuneration
 * @property int|null $commission_ugc
 * @property int|null $fedapay_transaction_id
 * @property string|null $payment_initiation_key
 * @property \Carbon\CarbonInterface|null $published_at First publication (set once by MissionObserver; drives new-mission notifications)
 * @property \Carbon\CarbonInterface|null $commission_paid_at
 * @property \Carbon\CarbonInterface|null $commission_refund_requested_at
 * @property \Carbon\CarbonInterface|null $commission_refunded_at
 * @property \App\Enums\UgcRefundReason|null $commission_refund_reason
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $candidatures_count
 * @property int|null $new_candidatures_count Candidatures created in the last 24 h (ProducerDashboardService::activeMissions)
 * @property int|null $confirmed_count Confirmed / in-progress / completed candidatures (ProducerDashboardService::activeMissions)
 * @property bool|null $has_paid_payment Pre-loaded by scopeWithPaidPaymentFlag (MissionResource, ProducerDashboardService::activeMissions)
 * @property-read \App\Models\Producer|null $producer
 * @property-read \App\Models\MissionPayment|null $payment
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Candidature> $candidatures
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProductPhoto> $productPhotos
 */
class Mission extends Model
{
    use HasFactory;
    use HasRouteUuid;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Mission $mission): void {
            if (empty($mission->slug)) {
                $mission->slug = self::generateUniqueSlug($mission->titre);
            }
        });

        static::updating(function (Mission $mission): void {
            if (
                $mission->isDirty('titre')
                && (
                    $mission->status === MissionStatus::Draft
                    || $mission->getOriginal('status') === MissionStatus::Draft->value
                )
            ) {
                $mission->slug = self::generateUniqueSlug($mission->titre, $mission->id);
            }
        });
    }

    /**
     * Generate a unique slug from the given title.
     */
    public static function generateUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $slug = Str::slug($title);

        if ($slug === '') {
            $slug = 'mission';
        }

        $original = $slug;
        $counter = 2;

        while (static::query()
            ->where('slug', $slug)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists()
        ) {
            $slug = "{$original}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'candidature_response_reminder_sent_at',
        'candidature_digest_sent_at',
        'producer_id',
        'titre',
        'description',
        'date_tournage',
        'shooting_reminder_sent_at',
        'profil_recherche',
        'budget',
        'date_limite_candidature',
        'nombre_faces_voulu',
        'type_mission',
        'type_mission_autre',
        'genre_voulu',
        'lieu',
        'duree',
        'status',
        'type_compensation',
        'nom_produit',
        'valeur_produit',
        'nombre_videos',
        'montant_remuneration',
        'commission_ugc',
        'fedapay_transaction_id',
        'payment_initiation_key',
        'commission_paid_at',
        'commission_refund_requested_at',
        'commission_refunded_at',
        'commission_refund_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_tournage' => 'date',
            'shooting_reminder_sent_at' => 'datetime',
            'date_limite_candidature' => 'date',
            'candidature_response_reminder_sent_at' => 'datetime',
            'candidature_digest_sent_at' => 'datetime',
            'budget' => 'integer',
            'nombre_faces_voulu' => 'integer',
            'status' => MissionStatus::class,
            'type_mission' => MissionType::class,
            'genre_voulu' => MissionGender::class,
            'type_compensation' => CompensationType::class,
            'valeur_produit' => 'integer',
            'nombre_videos' => 'integer',
            'montant_remuneration' => 'integer',
            'commission_ugc' => 'integer',
            'fedapay_transaction_id' => 'integer',
            'published_at' => 'datetime',
            'commission_paid_at' => 'datetime',
            'commission_refund_requested_at' => 'datetime',
            'commission_refunded_at' => 'datetime',
            'commission_refund_reason' => UgcRefundReason::class,
        ];
    }

    /**
     * Get the producer that owns this mission.
     */
    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }

    /**
     * Get the candidatures for this mission.
     */
    public function candidatures(): HasMany
    {
        return $this->hasMany(Candidature::class);
    }

    /**
     * Get the payment record for this mission.
     */
    public function payment(): HasOne
    {
        return $this->hasOne(MissionPayment::class);
    }

    /**
     * Photos produit de la dotation UGC (0-2, uploadées à la création — spec
     * photos produit). Rows sur disque `public` (colonne `disk`) : vitrine
     * visible des candidates, URLs asset directes.
     *
     * @return MorphMany<ProductPhoto, $this>
     */
    public function productPhotos(): MorphMany
    {
        return $this->morphMany(ProductPhoto::class, 'owner')->orderBy('position');
    }

    /**
     * Scope a query to only include missions with a specific status.
     */
    public function scopeStatus(Builder $query, MissionStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include draft missions.
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', MissionStatus::Draft);
    }

    /**
     * Scope a query to only include published missions.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', MissionStatus::Published);
    }

    /**
     * Pre-load the `has_paid_payment` flag read by MissionResource (no per-row query).
     */
    public function scopeWithPaidPaymentFlag(Builder $query): Builder
    {
        return $query->withExists([
            'payment as has_paid_payment' => fn ($q) => $q->where('status', MissionPaymentStatus::Paid),
        ]);
    }

    /**
     * Scope a query to only include missions pending payment.
     */
    public function scopePendingPayment(Builder $query): Builder
    {
        return $query->where('status', MissionStatus::PendingPayment);
    }

    /**
     * Scope a query to only include closed missions.
     */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', MissionStatus::Closed);
    }

    /**
     * Scope a query to exclude « obsolete » missions from browse listings: those
     * whose candidature deadline is passed OR whose shooting date is passed.
     *
     * The candidature-deadline part mirrors isAcceptingCandidatures() (>= today,
     * so a mission closing today is still listed ; « today » = date métier, fuseau
     * `app.business_timezone`, pas UTC) ; the shooting-date part hides
     * missions whose shoot has already happened. `date_tournage` is nullable
     * (UGC dotations have none) — a null shooting date never expires here.
     */
    public function scopeNotExpired(Builder $query): Builder
    {
        $today = self::businessToday();

        return $query
            ->whereDate('date_limite_candidature', '>=', $today)
            ->where(function (Builder $inner) use ($today) {
                $inner->whereNull('date_tournage')
                    ->orWhereDate('date_tournage', '>=', $today);
            });
    }

    /**
     * Scope a query to only include completed missions.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', MissionStatus::Completed);
    }

    /**
     * Scope a query to only include missions accepting candidatures
     * (deadline inclusive, jour calculé dans le fuseau métier `app.business_timezone`).
     */
    public function scopeAcceptingCandidatures(Builder $query): Builder
    {
        return $query->where('status', MissionStatus::Published)
            ->where('date_limite_candidature', '>=', self::businessToday());
    }

    /**
     * Filtre « À traiter » de la liste des missions d'un Producteur (lot D7) : missions où il doit
     * agir maintenant. `$producerId` = producers.id (missions.producer_id). Cas :
     *  - candidatures en attente de réponse, échéance de réponse (producerResponseDeadline) non écoulée ;
     *  - sélection à payer (mission standard en attente de paiement, délai de 24 h non écoulé) ;
     *  - commission UGC à payer (mission UGC en attente de paiement) ;
     *  - présences à valider (mission standard payée, jour de tournage atteint, présence encore en attente) ;
     *  - livrables UGC à valider (au moins une vidéo en revue).
     *
     * @param  Builder<Mission>  $query
     */
    public function scopeToProcessForProducer(Builder $query, int $producerId): void
    {
        $now = now();
        $ugc = MissionType::Ugc->value;

        $query->where('producer_id', $producerId)->where(function (Builder $cases) use ($now, $ugc): void {
            $cases
                ->orWhere(fn (Builder $b) => $b
                    ->where('status', MissionStatus::Published->value)
                    ->whereHas('candidatures', fn (Builder $c) => $c->where('status', CandidatureStatus::Pending->value))
                    // Clôture des candidatures (fin du dernier jour, heure métier) + 72 h, ou début du tournage s'il est plus tôt.
                    ->whereRaw(BusinessTimeSql::localInstant('date_limite_candidature', 24 + self::PRODUCER_RESPONSE_WINDOW_HOURS).' > ?', [$now])
                    ->where(fn (Builder $d) => $d
                        ->whereNull('date_tournage')
                        ->orWhereRaw(BusinessTimeSql::localInstant('date_tournage').' > ?', [$now])))
                ->orWhere(fn (Builder $b) => $b
                    ->where('status', MissionStatus::PendingPayment->value)
                    ->where('type_mission', '!=', $ugc)
                    ->whereHas('payment', fn (Builder $p) => $p
                        ->where('status', MissionPaymentStatus::Pending->value)
                        ->where('created_at', '>', $now->copy()->subHours(MissionPaymentService::SELECTION_PAYMENT_WINDOW_HOURS))))
                ->orWhere(fn (Builder $b) => $b
                    ->where('status', MissionStatus::PendingPayment->value)
                    ->where('type_mission', $ugc))
                ->orWhere(fn (Builder $b) => $b
                    ->whereIn('status', [MissionStatus::Closed->value, MissionStatus::PendingAttendanceValidation->value])
                    ->where('type_mission', '!=', $ugc)
                    ->where(fn (Builder $d) => $d
                        ->whereNull('date_tournage')
                        ->orWhereRaw(BusinessTimeSql::localInstant('date_tournage').' <= ?', [$now]))
                    ->whereHas('payment', fn (Builder $p) => $p
                        ->where('status', MissionPaymentStatus::Paid->value)
                        ->whereHas('entries', fn (Builder $e) => $e
                            ->where('escrow_status', EscrowStatus::Locked->value)
                            ->where('attendance_status', AttendanceStatus::Pending->value)
                            ->whereHas('candidature', fn (Builder $c) => $c->where('status', '!=', CandidatureStatus::Expired->value)))))
                ->orWhere(fn (Builder $b) => $b
                    ->whereIn('status', [MissionStatus::Published->value, MissionStatus::Closed->value])
                    ->where('type_mission', $ugc)
                    ->whereHas('candidatures', fn (Builder $c) => $c->whereHas('deliverables', fn (Builder $d) => $d
                        ->where('validation_status', DeliverableValidationStatus::InReview->value))));
        });
    }

    /**
     * Début du jour de tournage en fuseau métier (exprimé en UTC), null sans date de tournage.
     */
    public function shootingDayStartsAt(): ?CarbonInterface
    {
        if ($this->date_tournage === null) {
            return null;
        }

        return Carbon::parse($this->date_tournage->toDateString(), (string) config('app.business_timezone'))
            ->startOfDay()
            ->utc();
    }

    /** Lot B6 (D3) : délai de réponse du Producer aux candidatures, après leur clôture. */
    public const PRODUCER_RESPONSE_WINDOW_HOURS = 72;

    /** Lot B6 : le Producer est relancé une fois quand il reste au plus ce délai. */
    public const PRODUCER_RESPONSE_REMINDER_BEFORE_HOURS = 24;

    /**
     * Fin du dernier jour de candidature (date_limite_candidature incluse), fuseau métier,
     * exprimée en UTC. Null sans date limite.
     */
    public function candidaturesCloseAt(): ?CarbonInterface
    {
        if ($this->date_limite_candidature === null) {
            return null;
        }

        return Carbon::parse($this->date_limite_candidature->toDateString(), (string) config('app.business_timezone'))
            ->addDay()
            ->startOfDay()
            ->utc();
    }

    /**
     * Lot B6 (D3) : échéance de réponse du Producer aux candidatures en attente =
     * clôture des candidatures + 72 h, ou début du jour de tournage s'il arrive plus tôt.
     * Source unique (relance, expiration). Null sans date limite.
     */
    public function producerResponseDeadline(): ?CarbonInterface
    {
        $closeAt = $this->candidaturesCloseAt();

        if ($closeAt === null) {
            return null;
        }

        $deadline = $closeAt->copy()->addHours(self::PRODUCER_RESPONSE_WINDOW_HOURS);
        $shootingStart = $this->shootingDayStartsAt();

        return $shootingStart !== null && $shootingStart->lessThan($deadline) ? $shootingStart : $deadline;
    }

    /**
     * Échéance de réponse au format affiché (« 24/10 à 00:00 », fuseau métier).
     */
    public function producerResponseDeadlineLabel(): ?string
    {
        $deadline = $this->producerResponseDeadline();

        if ($deadline === null) {
            return null;
        }

        $local = $deadline->copy()->setTimezone((string) config('app.business_timezone'));

        return $local->format('d/m').' à '.$local->format('H:i');
    }

    /**
     * Lot B5 : les présences se pointent à partir du jour du tournage (fuseau métier).
     * Sans date de tournage (données anciennes), rien ne permet de bloquer : ouvert.
     */
    public function isAttendanceOpen(): bool
    {
        $start = $this->shootingDayStartsAt();

        return $start === null || now()->gte($start);
    }

    /**
     * Raison affichée quand les présences ne sont pas encore ouvertes, null sinon.
     */
    public function attendanceBlockedReason(): ?string
    {
        if ($this->isAttendanceOpen()) {
            return null;
        }

        return 'Les présences se valident à partir du jour du tournage (le '
            .$this->date_tournage?->format('d/m/Y').').';
    }

    /**
     * Date du jour (Y-m-d) dans le fuseau métier (Bénin), pas en UTC : la date
     * limite est un jour calendaire béninois.
     */
    public static function businessToday(): string
    {
        return Carbon::now((string) config('app.business_timezone'))->toDateString();
    }

    /**
     * Scope : missions dont le Producteur a un compte User actif.
     * Symbole partagé UNIQUE du filtre is_active (story 3.0) — toute surface
     * de découverte Face/Public passe par ce scope, jamais de whereHas inline.
     */
    public function scopeWhereProducerActive(Builder $query): Builder
    {
        return $query->whereHas('producer', fn ($q) => $q
            ->whereHas('user', fn ($u) => $u->where('is_active', true))
        );
    }

    /**
     * Check if the mission is currently accepting candidatures
     * (deadline inclusive, jour calculé dans le fuseau métier `app.business_timezone`).
     */
    public function isAcceptingCandidatures(): bool
    {
        return $this->status === MissionStatus::Published
            && $this->date_limite_candidature !== null
            && $this->date_limite_candidature->toDateString() >= self::businessToday();
    }

    /**
     * Le Producteur propriétaire est-il actif ? (gardes détail/apply, story 3.0)
     * Query dédiée — ne PAS loadMissing('producer.user') : ProducerResource
     * exposerait is_active via whenLoaded('user') (contrat API, D-3.0.d).
     */
    public function hasActiveProducer(): bool
    {
        return $this->producer()
            ->whereHas('user', fn ($u) => $u->where('is_active', true))
            ->exists();
    }

    /**
     * Lot C3 : statut affiché (le statut stocké reste inchangé).
     *  - pending_payment : UGC => « Commission à payer », standard => « Paiement en cours »
     *  - closed          : UGC => « En cours » (recrutement clos, tunnel actif) ; standard payée =>
     *                      « Payée, en préparation » ; standard sans paiement (clôture manuelle) => « Clôturée »
     *  - autres          : libellé du statut stocké.
     */
    public function displayStatus(bool $hasPaidPayment): MissionDisplayStatus
    {
        $isUgc = $this->type_mission === MissionType::Ugc;

        return match ($this->status) {
            MissionStatus::Draft => MissionDisplayStatus::Draft,
            MissionStatus::Published => MissionDisplayStatus::Published,
            MissionStatus::PendingPayment => $isUgc ? MissionDisplayStatus::CommissionDue : MissionDisplayStatus::PaymentInProgress,
            MissionStatus::Closed => match (true) {
                $isUgc => MissionDisplayStatus::InProgress,
                $hasPaidPayment => MissionDisplayStatus::PaidPreparing,
                default => MissionDisplayStatus::Closed,
            },
            MissionStatus::PendingAttendanceValidation => MissionDisplayStatus::AttendancePending,
            MissionStatus::Completed => MissionDisplayStatus::Completed,
        };
    }

    /**
     * Check if this mission has a pending (unpaid) payment record.
     */
    public function hasPendingPayment(): bool
    {
        return $this->status === MissionStatus::PendingPayment;
    }

    /**
     * Whether cash money is (or was) held for this mission: a paid MissionPayment, or any
     * escrow entry attached to a MissionPayment (parent set) that is Locked or already
     * settled (Released / Refunded). Parentless hybrid UGC entries are NOT cash and are
     * ignored. Such a mission can never be deleted nor reopened.
     */
    public function hasCashEscrow(): bool
    {
        $paid = MissionPayment::query()
            ->where('mission_id', $this->id)
            ->where('status', MissionPaymentStatus::Paid->value)
            ->exists();

        if ($paid) {
            return true;
        }

        return MissionPaymentCandidature::query()
            ->whereHas('missionPayment', fn (Builder $q) => $q->where('mission_id', $this->id))
            ->whereIn('escrow_status', [
                EscrowStatus::Locked->value,
                EscrowStatus::Released->value,
                EscrowStatus::Refunded->value,
            ])
            ->exists();
    }

    /**
     * Whether a cash entry still blocks completion: a Disputed entry (open dispute) or an
     * Absent one whose 72 h dispute window has not elapsed. A legacy Absent row without `notified_at`
     * counts as a closed window (the settle cron skips it too — treating it as open would freeze it forever).
     */
    public function hasOpenAttendanceDispute(): bool
    {
        return MissionPaymentCandidature::query()
            ->whereHas('missionPayment', fn (Builder $q) => $q->where('mission_id', $this->id))
            ->where('escrow_status', EscrowStatus::Locked->value)
            ->where(function (Builder $q): void {
                $q->where('attendance_status', AttendanceStatus::Disputed->value)
                    ->orWhere(function (Builder $absent): void {
                        $absent->where('attendance_status', AttendanceStatus::Absent->value)
                            ->where('notified_at', '>', now()->subHours(72));
                    });
            })
            ->exists();
    }

    /** Lot B4 : message du blocage « toutes les places remboursées » (aussi testé par la clôture automatique). */
    public const NO_FACE_CONFIRMED_MESSAGE = "Aucune Face n'a confirmé sa participation : il n'y a rien à terminer. Les places ont été remboursées sur votre wallet.";

    /**
     * First rule that prevents the Producer from completing this mission, as
     * [error key, message], or null when none applies. Single source of truth shared by
     * CompleteMissionRequest (422) and MissionResource::can_complete (UI button).
     *
     * @return array{0: string, 1: string}|null
     */
    public function completionBlocker(): ?array
    {
        $onlyClosed = 'Seules les missions clôturées peuvent être marquées comme terminées';

        // Lot B8 : une mission UGC n'a pas de MissionPayment (commission payée à la
        // publication, escrow hybride par candidature) ; règle dédiée.
        if ($this->type_mission === MissionType::Ugc) {
            return $this->ugcCompletionBlocker($onlyClosed);
        }

        if ($this->status === MissionStatus::Draft || $this->status === MissionStatus::Published) {
            return ['status', $onlyClosed];
        }

        if ($this->status === MissionStatus::PendingPayment) {
            return ['status', 'Le paiement doit être confirmé avant de terminer la mission'];
        }

        if ($this->status === MissionStatus::Completed) {
            return ['status', 'Cette mission est déjà terminée'];
        }

        // The 72h dispute window (or an open dispute) must be settled first — through the
        // crons / admin resolution — before the Producer can force the completion.
        if ($this->hasOpenAttendanceDispute()) {
            return ['status', 'Une absence est encore dans sa fenêtre de contestation de 72 h ou fait l\'objet d\'un litige : la mission ne peut pas être terminée pour le moment.'];
        }

        /** @var MissionPayment|null $payment */
        $payment = $this->payment;

        if (! $payment || $payment->status !== MissionPaymentStatus::Paid) {
            return ['status', 'Le paiement doit être confirmé avant de terminer la mission'];
        }

        $selectedCandidatureIds = $payment->entries()->pluck('candidature_id');

        if ($selectedCandidatureIds->isEmpty()) {
            return ['candidatures', 'Cette mission n\'a aucune candidature sélectionnée'];
        }

        $hasUnconfirmedSelectedFaces = Candidature::whereIn('id', $selectedCandidatureIds)
            ->where('status', CandidatureStatus::Accepted)
            ->exists();

        if ($hasUnconfirmedSelectedFaces) {
            return ['candidatures', 'Toutes les faces sélectionnées doivent confirmer leur participation avant de terminer la mission'];
        }

        // Lot B4 : une place libérée garde son argent en séquestre jusqu'à une remplaçante
        // ou son remboursement ; terminer la verserait à tort.
        $hasPendingFreedPlace = $payment->entries()
            ->whereNotNull('freed_at')
            ->where('escrow_status', EscrowStatus::Locked->value)
            ->exists();

        if ($hasPendingFreedPlace) {
            return ['candidatures', 'Une place libérée attend une remplaçante ou son remboursement : la mission ne peut pas encore être terminée.'];
        }

        // Lot B4 : toutes les Faces sélectionnées ont expiré (places remboursées) : rien à terminer.
        $hasEngagedFace = Candidature::whereIn('id', $selectedCandidatureIds)
            ->whereIn('status', [
                CandidatureStatus::Confirmed->value,
                CandidatureStatus::InProgress->value,
                CandidatureStatus::Completed->value,
            ])
            ->exists();

        if (! $hasEngagedFace) {
            return ['candidatures', self::NO_FACE_CONFIRMED_MESSAGE];
        }

        return null;
    }

    /**
     * Whether MissionService::completeMission would accept this mission right now
     * (its status gate + every CompleteMissionRequest rule).
     */
    public function canBeCompleted(): bool
    {
        return $this->hasCompletableStatus() && $this->completionBlocker() === null;
    }

    /**
     * Status gate of canBeCompleted(), without evaluating completionBlocker() (callers that already
     * hold the blocker avoid its queries, e.g. the Producer show summary).
     */
    public function hasCompletableStatus(): bool
    {
        // Lot B8 : une mission UGC dont le recrutement est fini peut être encore publiée
        // (date limite passée sans avoir rempli toutes les places).
        $completableStatuses = $this->type_mission === MissionType::Ugc
            ? [MissionStatus::Closed, MissionStatus::Published]
            : [MissionStatus::Closed, MissionStatus::PendingAttendanceValidation];

        return in_array($this->status, $completableStatuses, true);
    }

    /**
     * Lot B8 : statuts de candidature UGC encore engagés dans une livraison (non terminaux).
     *
     * @var list<CandidatureStatus>
     */
    public const UGC_ACTIVE_CANDIDATURE_STATUSES = [
        CandidatureStatus::Accepted,
        CandidatureStatus::Confirmed,
        CandidatureStatus::InProgress,
    ];

    /**
     * Lot B8 : règle de fin d'une mission UGC. Terminable quand le recrutement est fini
     * (clôturée, ou date limite de candidature passée), qu'aucune Face engagée n'est encore
     * en cours de livraison (acceptée, confirmée, en cours) et qu'au moins une livraison
     * est terminée. Aucun argent ne bouge à la fin : chaque candidature est déjà réglée.
     *
     * @return array{0: string, 1: string}|null
     */
    private function ugcCompletionBlocker(string $onlyClosed): ?array
    {
        if ($this->status === MissionStatus::Completed) {
            return ['status', 'Cette mission est déjà terminée'];
        }

        if ($this->status === MissionStatus::PendingPayment) {
            return ['status', 'La commission doit être réglée avant de terminer la mission'];
        }

        $recruitmentOver = $this->status === MissionStatus::Closed
            || ($this->status === MissionStatus::Published
                && $this->date_limite_candidature !== null
                && $this->date_limite_candidature->toDateString() < self::businessToday());

        if (! $recruitmentOver) {
            return ['status', $onlyClosed];
        }

        $hasActiveFaces = $this->candidatures()
            ->whereIn('status', array_map(
                static fn (CandidatureStatus $status): string => $status->value,
                self::UGC_ACTIVE_CANDIDATURE_STATUSES,
            ))
            ->exists();

        if ($hasActiveFaces) {
            return ['candidatures', 'Toutes les Faces engagées doivent avoir terminé leur livraison (ou quitté la mission) avant de terminer la mission.'];
        }

        if (! $this->candidatures()->where('status', CandidatureStatus::Completed->value)->exists()) {
            return ['candidatures', "Cette mission n'a aucune livraison terminée."];
        }

        return null;
    }

    /**
     * Count the candidatures that engage a slot of this mission's capacity (ugc-9-1, D-9.1.f).
     *
     * Engaged = accepted | confirmed | in_progress | completed — the same set
     * duplicated inline at the accept / settlement / refund call-sites. The reopen
     * helper (MissionPaymentService::reopenMissionIfSlotFreed) compares this count
     * against `nombre_faces_voulu` to decide whether a freed slot reopens the mission.
     */
    public function engagedCandidaturesCount(): int
    {
        return $this->candidatures()
            ->whereIn('status', [
                CandidatureStatus::Accepted->value,
                CandidatureStatus::Confirmed->value,
                CandidatureStatus::InProgress->value,
                CandidatureStatus::Completed->value,
            ])
            ->count();
    }
}
