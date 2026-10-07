<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Adds a UUID column for external route resolution while keeping integer PKs for internal relations.
 *
 * Usage: `use HasRouteUuid;` in any Eloquent model that needs UUID-based route binding.
 */
trait HasRouteUuid
{
    protected static function bootHasRouteUuid(): void
    {
        static::creating(function (Model $model): void {
            if (blank($model->getAttribute('uuid'))) {
                $model->setAttribute('uuid', (string) Str::uuid());
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field ??= $this->getRouteKeyName();

        // No integer-id fallback: sequential ids would make every route
        // enumerable. Binding is by uuid (or the explicit custom field) only.
        return $this->newQuery()
            ->where($field, $value)
            ->first();
    }
}
