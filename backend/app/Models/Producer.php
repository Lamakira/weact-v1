<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProducerType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Producer extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'agency_name',
        'first_name',
        'last_name',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProducerType::class,
        ];
    }

    /**
     * Get the user associated with this Producer.
     */
    public function user(): MorphOne
    {
        return $this->morphOne(User::class, 'userable');
    }

    /**
     * Check if this producer is an agency.
     */
    public function isAgency(): bool
    {
        return $this->type === ProducerType::Agency;
    }

    /**
     * Check if this producer is a particulier (individual).
     */
    public function isParticulier(): bool
    {
        return $this->type === ProducerType::Particulier;
    }
}

