<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawalRequest extends Model
{
    use HasFactory;

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
