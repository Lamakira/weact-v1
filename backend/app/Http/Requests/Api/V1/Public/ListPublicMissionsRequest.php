<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Public;

use Illuminate\Foundation\Http\FormRequest;

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
}
