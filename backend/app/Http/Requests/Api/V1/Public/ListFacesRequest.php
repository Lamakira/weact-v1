<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Public;

use App\Constants\BeninCities;
use App\Enums\FaceCategory;
use App\Enums\FaceNiche;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request for public faces list endpoint.
 *
 * Validates pagination and filter parameters for the public faces listing.
 * No authentication required as this is a public endpoint.
 */
class ListFacesRequest extends FormRequest
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
            'per_page' => ['sometimes', 'integer', 'min:1'],
            'categorie' => ['sometimes', 'nullable', Rule::enum(FaceCategory::class)],
            'niche' => ['sometimes', 'nullable', Rule::enum(FaceNiche::class)],
            'ville' => ['sometimes', 'nullable', 'string', Rule::in(BeninCities::values())],
            'search' => ['sometimes', 'nullable', 'string', 'min:2', 'max:255'],
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
