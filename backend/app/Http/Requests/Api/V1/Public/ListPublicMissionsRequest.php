<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Public;

use App\Enums\MissionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Form Request for public missions list endpoint.
 *
 * Validates pagination parameters for the public missions listing.
 * No authentication required as this is a public endpoint.
 */
class ListPublicMissionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Always true for public endpoints.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'type_mission' => ['sometimes', 'nullable', Rule::enum(MissionType::class)],
            'lieu' => ['sometimes', 'nullable', 'string', 'max:100'],
            'budget_min' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'budget_max' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Ensure budget_max is only compared to budget_min when both are present.
     *
     * @return array<int, \Closure(\Illuminate\Validation\Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $budgetMin = $this->input('budget_min');
                $budgetMax = $this->input('budget_max');

                if ($budgetMin !== null && $budgetMin !== ''
                    && $budgetMax !== null && $budgetMax !== ''
                    && (int) $budgetMax < (int) $budgetMin) {
                    $validator->errors()->add(
                        'budget_max',
                        'Le budget maximum doit être supérieur ou égal au budget minimum.'
                    );
                }
            },
        ];
    }

    /**
     * Get validated per_page value with default, capped at 30.
     */
    public function getPerPage(): int
    {
        $perPage = (int) ($this->validated()['per_page'] ?? 15);

        return min($perPage, 30);
    }

    /**
     * Get validated page value with default.
     */
    public function getPage(): int
    {
        return (int) $this->validated('page', 1);
    }

    /**
     * Get validated mission filters for the public listing endpoint.
     *
     * @return array<string, string|int>
     */
    public function getFilters(): array
    {
        return array_filter([
            'type_mission' => $this->input('type_mission'),
            'lieu' => $this->input('lieu'),
            'budget_min' => $this->input('budget_min'),
            'budget_max' => $this->input('budget_max'),
        ], fn ($value): bool => $value !== null && $value !== '');
    }
}
