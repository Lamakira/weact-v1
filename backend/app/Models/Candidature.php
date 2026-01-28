<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CandidatureStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Candidature extends Model
{
    use HasFactory;

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
        $conversation = $this->conversation;

        if (!$conversation) {
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
}
