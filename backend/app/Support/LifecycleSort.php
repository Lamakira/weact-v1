<?php

declare(strict_types=1);

namespace App\Support;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tri d'une colonne de statut selon l'ordre de cycle de vie (FIELD()), au lieu de
 * l'ordre alphabétique de la clé anglaise ou de l'index ENUM MySQL.
 */
final class LifecycleSort
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<BackedEnum>  $order
     */
    public static function apply(Builder $query, string $column, array $order, string $direction): void
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';
        $placeholders = implode(', ', array_fill(0, count($order), '?'));

        $query->orderByRaw(
            "FIELD(`{$column}`, {$placeholders}) {$direction}",
            array_map(fn (BackedEnum $case) => $case->value, $order),
        );
    }
}
