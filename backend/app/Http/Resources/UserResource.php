<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Admin;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /**
     * True when the caller guarantees the rendered account IS the authenticated
     * account (login, register, Google exchange, /user). Never derived from the
     * request: a stale session can make `$request->user()` another account.
     */
    private bool $renderedForOwner = false;

    /**
     * Render an account for itself (owner view: email, verification, has_password).
     */
    public static function forOwner(User $user): self
    {
        $resource = new self($user);
        $resource->renderedForOwner = true;

        return $resource;
    }

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

        // PII: the account identifiers (email, verification state, internal
        // userable_id, is_active) belong to the account owner and to admins only.
        // This resource is also rendered for the OTHER party of a booking. The auth
        // responses (login, register, Google, /user) opt in through forOwner().
        // Admin and User ids live in different tables, so identity is checked on
        // the class, not on the bare id.
        $viewer = $request->user();
        $isOwner = $this->renderedForOwner
            || ($viewer instanceof User && $viewer->id === $this->id);
        $isPrivileged = $viewer instanceof Admin || $isOwner;

        $data = [
            'id' => $this->id,
            ...($isPrivileged ? [
                'email' => $this->email,
                'is_active' => $this->is_active,
            ] : []),
            'userable_type' => $this->getReadableUserableType(),
            ...($isPrivileged ? ['userable_id' => $this->userable_id] : []),
            'userable' => $this->whenLoaded('userable', function () {
                return $this->transformUserable();
            }),
            ...($isPrivileged ? [
                'email_verified' => $this->hasVerifiedEmail(),
                'email_verified_at' => $emailVerifiedAt?->toIso8601String(),
            ] : []),
            'created_at' => $createdAt?->toIso8601String(),
            'updated_at' => $updatedAt?->toIso8601String(),
        ];

        // The SPA needs all three decisions (password form title/CTA, email form
        // disabled state, delete-account copy) and would otherwise probe for them.
        // Owner-only: it means "signs in with Google only", which the other party
        // of a booking (rendered through this same resource) must not learn.
        if ($isOwner) {
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
