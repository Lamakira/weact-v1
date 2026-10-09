<?php

declare(strict_types=1);

namespace App\Http\Requests\Mission;

use App\Enums\MissionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Paramètres de tri / filtre / pagination (opt-in) de GET /api/v1/producer/missions.
 *
 * Valeurs hors allowlist => 422.
 */
class IndexProducerMissionsRequest extends FormRequest
{
    public const PER_PAGE_OPTIONS = [10, 15, 25, 50];

    public const DEFAULT_PER_PAGE = 15;

    public const SORT_KEYS = ['created_at', 'date_tournage', 'date_limite_candidature', 'status', 'candidatures_count'];

    public function authorize(): bool
    {
        // Le contrôle Producer (403) reste dans le contrôleur.
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
            'status' => ['sometimes', 'string', Rule::enum(MissionStatus::class)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', Rule::in(self::PER_PAGE_OPTIONS)],
        ];
    }

    /**
     * La pagination est opt-in : la réponse historique (tableau complet) est
     * conservée tant que ni `page` ni `per_page` ne sont fournis.
     */
    public function wantsPagination(): bool
    {
        return $this->has('page') || $this->has('per_page');
    }
}
