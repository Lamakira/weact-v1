<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Face;
use App\Models\Producer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Carbon|null $emailVerifiedAt */
        $emailVerifiedAt = $this->email_verified_at;
        /** @var Carbon|null $createdAt */
        $createdAt = $this->created_at;
        /** @var Carbon|null $updatedAt */
        $updatedAt = $this->updated_at;

        $data = [
            'id' => $this->id,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'userable_type' => $this->getReadableUserableType(),
            'userable_id' => $this->userable_id,
            'userable' => $this->whenLoaded('userable', function () {
                return $this->transformUserable();
            }),
            'email_verified' => $this->hasVerifiedEmail(),
            'email_verified_at' => $emailVerifiedAt?->toIso8601String(),
            'created_at' => $createdAt?->toIso8601String(),
            'updated_at' => $updatedAt?->toIso8601String(),
        ];

        // The SPA needs all three decisions (password form title/CTA, email form
        // disabled state, delete-account copy) and would otherwise probe for them.
        // Owner-only: it means "signs in with Google only", which the other party
        // of a booking (rendered through this same resource) must not learn.
        // `user() === null` covers the unauthenticated auth responses (login,
        // register, Google exchange/complete), which return the account that just
        // authenticated.
        $viewer = $request->user();

        if ($viewer === null || $viewer->id === $this->id) {
            $data['has_password'] = $this->password !== null;
        }

        return $data;
    }

    /**
     * Get a readable userable type name.
     */
    private function getReadableUserableType(): ?string
    {
        return match ($this->userable_type) {
            Face::class => 'Face',
            Producer::class => 'Producer',
            default => $this->userable_type,
        };
    }

    /**
     * Transform the userable relationship.
     */
    private function transformUserable(): mixed
    {
        return match ($this->userable_type) {
            Face::class => new FaceResource($this->userable),
            Producer::class => new ProducerResource($this->userable),
            default => $this->userable,
        };
    }
}
