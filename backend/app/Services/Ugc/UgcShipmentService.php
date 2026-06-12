<?php

declare(strict_types=1);

namespace App\Services\Ugc;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\MissionType;
use App\Enums\UgcTunnelStatus;
use App\Events\ProductReceived;
use App\Events\ShipmentConfirmed;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Shipment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Confirmation d'expédition d'un deal UGC (FR6 étape 3) : crée le Shipment
 * polymorphe (owner Booking accepté | Candidature confirmée), fige le snapshot
 * destinataire et ouvre le micro-tunnel à `shipped`. Transitions gardées
 * idempotentes sous lock owner (AR2) ; unique DB en backstop. Le dispatch
 * ShipmentConfirmed est POST-COMMIT (D-3.1.i, leçon AC6↔S-3 de 2.5).
 */
class UgcShipmentService
{
    /**
     * @param  array{transporteur: string, numero_suivi: string, note_envoi?: string|null}  $data
     * @return array{outcome: string, shipment?: Shipment}
     */
    public function confirm(Booking|Candidature $owner, array $data): array
    {
        $result = DB::transaction(function () use ($owner, $data): array {
            if ($owner instanceof Booking) {
                /** @var Booking $locked */
                $locked = Booking::query()->lockForUpdate()->findOrFail($owner->id);
                $guard = $this->guardBooking($locked);
                $face = $locked->face?->userable; // face_id = users.id → User->userable = Face
            } else {
                /** @var Candidature $locked */
                $locked = Candidature::query()->lockForUpdate()->findOrFail($owner->id);
                $guard = $this->guardCandidature($locked);
                $face = $locked->face; // face_id = faces.id → Face direct
            }

            if ($guard !== null) {
                return ['outcome' => $guard];
            }

            // Idempotence : re-check sous lock (sérialisé par le lock owner).
            if ($locked->shipment()->exists()) {
                return ['outcome' => 'already'];
            }

            if (! $face instanceof Face) {
                Log::critical('UGC shipment: Face introuvable pour le snapshot destinataire', [
                    'owner_type' => $locked::class,
                    'owner_id' => $locked->id,
                ]);

                return ['outcome' => 'invalid_status'];
            }

            try {
                $shipment = $locked->shipment()->create([
                    'transporteur' => $data['transporteur'],
                    'numero_suivi' => $data['numero_suivi'],
                    'note_envoi' => $data['note_envoi'] ?? null,
                    'tunnel_status' => UgcTunnelStatus::Shipped,
                    'shipped_at' => now(),
                    // prenom/nom font 255 chacun — la concat peut dépasser la colonne (255).
                    'destinataire_nom' => Str::limit(trim($face->prenom.' '.$face->nom), 255, ''),
                    'destinataire_ville' => $face->ville,
                    'destinataire_pays' => $face->pays,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Backstop shipments_owner_unique (D-3.1.b) : un writer hors-lock
                // a gagné la course — même contrat que le re-check exists().
                return ['outcome' => 'already'];
            }

            return ['outcome' => 'confirmed', 'shipment' => $shipment];
        });

        // Post-commit : un rollback ne doit pas notifier (D-2.4.f reconduite).
        if ($result['outcome'] === 'confirmed') {
            ShipmentConfirmed::dispatch($result['shipment']);
        }

        return $result;
    }

    /**
     * « Produit reçu » (FR6 étape 4) : fige recu_le (ancre du chrono Unboxing,
     * dérivé — D-3.3.a) et ouvre `received`. Idempotent sur le FAIT de
     * réception (recu_le, D-3.3.d) ; gardes owner ré-exécutées sous
     * transaction (statut + refund — action #5 rétro). Dispatch POST-COMMIT.
     *
     * @return array{outcome: string, shipment?: Shipment}
     */
    public function markReceived(Shipment $shipment): array
    {
        $result = DB::transaction(function () use ($shipment): array {
            /** @var Shipment $locked */
            $locked = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);

            // Idempotence sur le FAIT de réception : un second clic ne
            // réinitialise JAMAIS le chrono, quelle que soit la position du
            // tunnel (qui avancera aux épics 4-5).
            if ($locked->recu_le !== null) {
                return ['outcome' => 'already'];
            }

            if ($locked->tunnel_status !== UgcTunnelStatus::Shipped) {
                return ['outcome' => 'invalid_status'];
            }

            $owner = $locked->owner;

            $guard = match (true) {
                $owner instanceof Booking => $this->guardBooking($owner),
                $owner instanceof Candidature => $this->guardCandidature($owner),
                default => 'invalid_status',
            };

            if ($guard !== null) {
                return ['outcome' => $guard];
            }

            $locked->update([
                'tunnel_status' => UgcTunnelStatus::Received,
                'recu_le' => now(),
            ]);

            return ['outcome' => 'received', 'shipment' => $locked];
        });

        // Post-commit : un rollback ne doit pas notifier (D-2.4.f reconduite).
        if ($result['outcome'] === 'received') {
            ProductReceived::dispatch($result['shipment']);
        }

        return $result;
    }

    private function guardBooking(Booking $booking): ?string
    {
        if ($booking->type_contenu !== 'UGC' || $booking->status !== BookingStatus::Accepted) {
            return 'invalid_status';
        }

        // Propagation D-2.5.h (action #5 rétro) : refund demandé OU réglé
        // hors-procédure → on n'expédie pas un deal en cours de remboursement.
        if ($booking->commission_refund_requested_at !== null || $booking->commission_refunded_at !== null) {
            return 'refund_in_progress';
        }

        return null;
    }

    private function guardCandidature(Candidature $candidature): ?string
    {
        $mission = $candidature->mission;

        // PAS de garde Published : une mission auto-close à capacité (2.4)
        // porte des engagements actifs expédiables (piège n°1).
        if ($candidature->status !== CandidatureStatus::Confirmed
            || $mission === null
            || $mission->type_mission !== MissionType::Ugc
            || $mission->commission_paid_at === null) {
            return 'invalid_status';
        }

        if ($mission->commission_refund_requested_at !== null || $mission->commission_refunded_at !== null) {
            return 'refund_in_progress';
        }

        return null;
    }
}
