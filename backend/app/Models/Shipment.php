<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasRouteUuid;
use App\Enums\UgcTunnelStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Expédition d'un deal UGC (tunnel étape 3) — owner polymorphe :
 * Booking (deal direct) | Candidature (Face engagée sur mission UGC).
 * Porte le micro-tunnel post-expédition via tunnel_status (D-3.1.a).
 * Snapshot destinataire figé à la confirmation (D-3.1.f).
 *
 * @property int $id
 * @property string $uuid
 * @property string $owner_type
 * @property int $owner_id
 * @property string $transporteur
 * @property string $numero_suivi
 * @property string|null $note_envoi
 * @property \App\Enums\UgcTunnelStatus $tunnel_status
 * @property \Illuminate\Support\Carbon $shipped_at
 * @property \Illuminate\Support\Carbon|null $recu_le
 * @property string $destinataire_nom
 * @property string|null $destinataire_ville
 * @property string|null $destinataire_pays
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Booking|Candidature|null $owner
 */
class Shipment extends Model
{
    use HasRouteUuid;

    /**
     * Clés morph volontairement HORS fillable : posées par la relation
     * $owner->shipment()->create(), jamais mass-assignées depuis l'input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'transporteur',
        'numero_suivi',
        'note_envoi',
        'tunnel_status',
        'shipped_at',
        'recu_le',
        'destinataire_nom',
        'destinataire_ville',
        'destinataire_pays',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tunnel_status' => UgcTunnelStatus::class,
            'shipped_at' => 'datetime',
            'recu_le' => 'datetime',
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
