<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasRouteUuid;
use App\Enums\CandidatureStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $uuid
 * @property int $id
 * @property int $face_id
 * @property int $mission_id
 * @property string|null $message_motivation
 * @property \App\Enums\CandidatureStatus $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read \App\Models\Face|null $face
 * @property-read \App\Models\Mission|null $mission
 * @property-read \App\Models\Conversation|null $conversation
 * @property-read \App\Models\MissionPaymentCandidature|null $paymentEntry
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
