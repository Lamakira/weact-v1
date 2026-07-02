<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MissionPaymentStatus;
use App\Exceptions\MoneyColumnImmutableException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $producer_id
 * @property int $montant_total_producteur
 * @property int|null $fedapay_transaction_id
 * @property \App\Enums\MissionPaymentStatus $status
 * @property \Carbon\CarbonInterface|null $paid_at
 * @property-read \App\Models\Mission|null $mission
 * @property-read \App\Models\Producer|null $producer
 */
class MissionPayment extends Model
{
    protected $fillable = [
        'mission_id',
        'producer_id',
        'nombre_faces_retenues',
        'budget_par_face',
        'montant_sous_total',
        'commission_producteur',
        'montant_total_producteur',
        'commission_faces_total',
        'montant_total_faces',
        'fedapay_transaction_id',
        'fedapay_ref',
        'status',
        'paid_at',
    ];

    /**
     * Colonnes de montant immuables après création (durcissement ugc-3-5).
     *
     * @var list<string>
     */
    private const IMMUTABLE_MONEY_COLUMNS = [
        'budget_par_face',
        'montant_sous_total',
        'commission_producteur',
        'montant_total_producteur',
        'commission_faces_total',
        'montant_total_faces',
    ];

    protected static function booted(): void
    {
        static::updating(function (MissionPayment $model): void {
            if ($model->isDirty(self::IMMUTABLE_MONEY_COLUMNS)) {
                throw new MoneyColumnImmutableException(
                    $model::class.' : colonnes de montant immuables après création ('
                    .implode(', ', array_keys(array_intersect_key(
                        $model->getDirty(),
                        array_flip(self::IMMUTABLE_MONEY_COLUMNS)
                    ))).')'
                );
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => MissionPaymentStatus::class,
            'paid_at' => 'datetime',
            'nombre_faces_retenues' => 'integer',
            'budget_par_face' => 'integer',
            'montant_sous_total' => 'integer',
            'commission_producteur' => 'integer',
            'montant_total_producteur' => 'integer',
            'commission_faces_total' => 'integer',
            'montant_total_faces' => 'integer',
        ];
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(MissionPaymentCandidature::class);
    }
}
