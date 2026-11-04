<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasRouteUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Défi de signature par code e-mail à 6 chiffres. Le code n'est jamais stocké en clair.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $purpose
 * @property string $email
 * @property string $code_hash
 * @property \Illuminate\Support\Carbon $expires_at
 * @property int $attempts
 * @property \Illuminate\Support\Carbon|null $consumed_at
 * @property string|null $guardian_name
 * @property string|null $guardian_relation
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class SignatureChallenge extends Model
{
    use HasRouteUuid;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'purpose',
        'email',
        'code_hash',
        'expires_at',
        'attempts',
        'consumed_at',
        'guardian_name',
        'guardian_relation',
    ];

    /** @var list<string> */
    protected $hidden = ['code_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
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
