<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Booking as seen by an admin — an oversight view, NOT a party view.
 *
 * Deliberately separate from BookingResource: that one depends on Auth::user()
 * being the Face or the Producer (can_* flags, fedapay masking, rater rating).
 * An admin is neither party, so it exposes everything an oversight role needs —
 * both parties (name + email), full amounts, escrow, payment — with no per-user
 * masking. Read-only: no action affordances.
 *
 * @mixin \App\Models\Booking
 */
class AdminBookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),

            // Parties — both sides, with email (admin is a trusted oversight role).
            'face' => $this->formatParty($this->whenLoaded('face')),
            'producer' => $this->formatParty($this->whenLoaded('producer')),

            // Nature of the booking.
            'type_contenu' => $this->type_contenu,
            'type_compensation' => $this->type_compensation?->value,
            'type_compensation_label' => $this->type_compensation?->label(),
            'nom_produit' => $this->nom_produit,
            'valeur_produit' => $this->valeur_produit,
            'nombre_videos' => $this->nombre_videos,
            'lieu' => $this->lieu,
            'message' => $this->message,

            // Money — the point of an admin view; nothing masked.
            'tarif_base' => $this->tarif_base,
            'montant_total_producteur' => $this->montant_total_producteur,
            'montant_face_recoit' => $this->montant_face_recoit,
            'montant_remuneration' => $this->montant_remuneration,
            'commission_ugc' => $this->commission_ugc,
            'payment_mode' => $this->payment_mode,
            'fedapay_transaction_id' => $this->fedapay_transaction_id,
            'commission_paid_at' => $this->commission_paid_at?->toIso8601String(),

            // Commission refund trail (UGC).
            'commission_refund_requested_at' => $this->commission_refund_requested_at?->toIso8601String(),
            'commission_refunded_at' => $this->commission_refunded_at?->toIso8601String(),
            'commission_refund_reason' => $this->commission_refund_reason?->value,
            'commission_refund_reason_label' => $this->commission_refund_reason?->label(),

            // Escrow — omitted from the payload when the relation was not loaded.
            'escrow' => $this->whenLoaded('escrowTransaction', function () {
                /** @var \App\Models\EscrowTransaction|null $escrow */
                $escrow = $this->escrowTransaction;

                return $escrow ? [
                    'status' => $escrow->status,
                    'amount' => $escrow->amount,
                    'locked_at' => $escrow->locked_at?->toIso8601String(),
                    'released_at' => $escrow->released_at?->toIso8601String(),
                    'refunded_at' => $escrow->refunded_at?->toIso8601String(),
                ] : null;
            }),

            // Cancellation.
            'cancellation_reason' => $this->cancellation_reason,
            'custom_cancellation_reason' => $this->custom_cancellation_reason,

            // Timeline.
            'date_debut' => $this->date_debut?->toIso8601String(),
            'date_fin' => $this->date_fin?->toIso8601String(),
            'duree_heures' => $this->duree_heures,
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Compact party summary: id, resolved name, email, role. The name lives on
     * the polymorphic userable (Face: prénom+nom, Producer: display_name), so
     * `<party>.userable` must be eager-loaded by the controller.
     *
     * @return array<string, mixed>|null
     */
    private function formatParty(mixed $user): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        $userable = $user->userable;
        $name = match (true) {
            $userable instanceof Face => trim((string) ($userable->prenom ?? '').' '.(string) ($userable->nom ?? '')),
            $userable instanceof Producer => $userable->display_name,
            default => '',
        };
        $role = match ($user->userable_type) {
            Face::class => 'Face',
            Producer::class => 'Producer',
            default => null,
        };

        return [
            'id' => $user->id,
            'name' => $name !== '' ? $name : null,
            'email' => $user->email,
            'role' => $role,
        ];
    }
}
