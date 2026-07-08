<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\FaceSubscriptionAudit
 */
class AdminFaceSubscriptionAuditResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $admin = $this->admin;

        return [
            'id' => $this->uuid,
            'action' => $this->action->value,
            'action_label' => $this->action->label(),
            'notes' => $this->notes,
            'previous_state' => $this->previous_state,
            'new_state' => $this->new_state,
            'admin' => [
                'id' => $admin?->uuid,
                'name' => $admin->name ?? 'Admin supprimé',
                'role' => $admin?->role?->value,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
