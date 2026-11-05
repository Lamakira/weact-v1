<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts a value of a closed territory list (App\Support\Territories), or a
 * value identical to the one already stored: a legacy free-text value that has
 * not been corrected yet must not block an unrelated save.
 */
class InTerritoryList implements ValidationRule
{
    /**
     * @param  list<string>  $list
     */
    public function __construct(
        private readonly array $list,
        private readonly ?string $current = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && (in_array($value, $this->list, true) || ($this->current !== null && $value === $this->current))) {
            return;
        }

        $fail('Choisissez une valeur de la liste.');
    }
}
