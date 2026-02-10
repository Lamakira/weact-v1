<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Public;

use App\Enums\ArticleCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request for public articles list endpoint.
 *
 * Validates pagination parameters for the public articles listing.
 * No authentication required as this is a public endpoint.
 */
class ListPublicArticlesRequest extends FormRequest
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
            'category' => ['sometimes', 'nullable', Rule::enum(ArticleCategory::class)],
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
}
