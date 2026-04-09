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
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
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

        $boundModel = $this->newQuery()
            ->where($field, $value)
            ->first();

        if ($boundModel !== null || $field !== $this->getRouteKeyName() || ! is_numeric($value)) {
            return $boundModel;
        }

        return $this->newQuery()
            ->whereKey((int) $value)
            ->first();
    }
}
