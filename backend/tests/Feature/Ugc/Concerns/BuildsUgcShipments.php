<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc\Concerns;

use App\Enums\UgcTunnelStatus;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Shipment;
use Illuminate\Http\UploadedFile;

/**
 * Fixtures shipment partagées entre les tests UGC mission/booking (dédup, ugc-3-5 Item 5).
 */
trait BuildsUgcShipments
{
    /**
     * Payload multipart pour confirm-receipt : 1-2 photos de réception valides
     * (spec réception — l'obligation vit dans ConfirmReceiptRequest, donc tout
     * chemin HTTP heureux DOIT joindre des photos).
     *
     * @return array<string, list<UploadedFile>>
     */
    protected function receiptPhotos(int $count = 1): array
    {
        return [
            'reception_photos' => array_map(
                fn (int $i) => UploadedFile::fake()->image("reception-{$i}.jpg", 800, 800),
                range(1, $count),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function confirmPayload(array $overrides = []): array
    {
        return array_merge([
            'transporteur' => 'Gozem',
            'numero_suivi' => 'GZM-COT-882194',
            'note_envoi' => 'Le colis arrive demain entre 14h et 16h.',
        ], $overrides);
    }

    /**
     * Shipment `shipped` posé directement (le POST producer confirm-shipment est déjà couvert par les tests 3.1).
     */
    protected function makeShippedShipment(Booking|Candidature $owner): Shipment
    {
        return $owner->shipment()->create([
            'transporteur' => 'Gozem',
            'numero_suivi' => 'GZM-COT-882194',
            'tunnel_status' => UgcTunnelStatus::Shipped,
            'shipped_at' => now()->subDay(),
            'destinataire_nom' => 'Aïcha Bello',
            'destinataire_ville' => 'Cotonou',
            'destinataire_pays' => 'Bénin',
        ]);
    }
}
