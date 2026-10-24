<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal d'une tentative d'envoi WhatsApp (jamais le texte du message).
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $template
 * @property string $to
 * @property string|null $context
 * @property string|null $wamid
 * @property string $status
 * @property string|null $error_code
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $status_updated_at
 */
class WhatsappMessage extends Model
{
    public const QUEUED = 'queued';

    public const SENT = 'sent';

    public const DELIVERED = 'delivered';

    public const READ = 'read';

    public const FAILED = 'failed';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'template',
        'to',
        'context',
        'wamid',
        'status',
        'error_code',
        'error_message',
        'status_updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
