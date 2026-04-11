<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasRouteUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $uuid
 * @property int $candidature_id
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \App\Models\Candidature|null $candidature
 * @property-read \App\Models\Face|null $face
 * @property-read \App\Models\Producer|null $producer
 * @property-read \App\Models\Message|null $latestMessage
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Message> $messages
 */
class Conversation extends Model
{
    use HasFactory;
    use HasRouteUuid;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'candidature_id',
    ];

    /**
     * Get the candidature this conversation belongs to.
     */
    public function candidature(): BelongsTo
    {
        return $this->belongsTo(Candidature::class);
    }

    /**
     * Get the messages in this conversation, ordered by creation time.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    /**
     * Get the latest message in this conversation.
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Get the Face participant through the candidature relationship.
     */
    public function getFaceAttribute(): ?Face
    {
        return $this->candidature?->face;
    }

    /**
     * Get the Producer participant through the candidature->mission relationship.
     */
    public function getProducerAttribute(): ?Producer
    {
        return $this->candidature?->mission?->producer;
    }

    /**
     * Get the count of unread messages for a specific user.
     *
     * Only counts messages that were NOT sent by the user.
     * Since all senders are User type, we only need to check sender_id.
     */
    public function unreadCountFor(User $user): int
    {
        return $this->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $user->id)
            ->count();
    }
}
