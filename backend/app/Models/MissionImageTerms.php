<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Conditions d'utilisation de l'image d'une mission, signées une seule fois par le Producteur à
 * la publication. Elles ne changent plus ensuite ; le contrat ne naît qu'à la signature de la Face.
 * Table séparée : ne touche ni `$fillable` ni `$casts` de Mission.
 *
 * @property int $id
 * @property int $mission_id
 * @property string $template_version
 * @property string $finalite
 * @property list<string> $supports
 * @property string $territoire
 * @property int $duree_annees
 * @property string $producer_signed_name
 * @property \Illuminate\Support\Carbon $producer_signed_at
 * @property string $producer_signed_email_masked
 * @property string|null $producer_user_agent
 * @property-read Mission|null $mission
 */
class MissionImageTerms extends Model
{
    protected $table = 'mission_image_terms';

    /** @var list<string> */
    protected $fillable = [
        'mission_id',
        'template_version',
        'finalite',
        'supports',
        'territoire',
        'duree_annees',
        'producer_signed_name',
        'producer_signed_at',
        'producer_signed_email_masked',
        'producer_user_agent',
    ];

    /** @var list<string> */
    protected $hidden = ['producer_user_agent'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'supports' => 'array',
            'duree_annees' => 'integer',
            'producer_signed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Mission, $this>
     */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }
}
