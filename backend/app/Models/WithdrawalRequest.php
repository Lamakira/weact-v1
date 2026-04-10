<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasRouteUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $uuid
 * @property int $amount
 * @property string $payment_mode
 * @property string $phone_number
 * @property string|null $phone_country
 * @property string $status
 * @property string|null $notes
 * @property int|null $wallet_transaction_id
 * @property int|null $processed_by_admin_id
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property User $user
 */
class WithdrawalRequest extends Model
{
    use HasFactory, HasRouteUuid;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'amount',
        'payment_mode',
        'phone_number',
        'phone_country',
        'status',
        'notes',
        'wallet_transaction_id',
        'processed_by_admin_id',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'processed_by_admin_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
