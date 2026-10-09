<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Paramètres de tri / pagination de GET /api/v1/bookings.
 *
 * Valeurs hors allowlist => 422 (jamais ignorées silencieusement).
 */
class IndexBookingsRequest extends FormRequest
{
    public const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    public const DEFAULT_PER_PAGE = 15;

    public const SORT_KEYS = ['date_debut', 'created_at', 'montant', 'status'];

    public function authorize(): bool
    {
        // L'autorisation métier (viewAny) reste dans le contrôleur.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sort' => ['sometimes', 'string', Rule::in(self::SORT_KEYS)],
            'direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'integer', Rule::in(self::PER_PAGE_OPTIONS)],
        ];
    }
}
