<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Face;
use App\Models\Producer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
        return [
            'id' => $this->id,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'userable_type' => $this->getReadableUserableType(),
            'userable' => $this->whenLoaded('userable', function () {
                return $this->transformUserable();
            }),
            'email_verified' => $this->hasVerifiedEmail(),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
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
