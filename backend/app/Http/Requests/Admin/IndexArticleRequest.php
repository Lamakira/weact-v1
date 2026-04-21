<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ArticleCategory;
use App\Enums\ArticleStatus;
use App\Models\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof Admin;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', Rule::in(ArticleCategory::values())],
            'status' => ['sometimes', 'nullable', Rule::in(ArticleStatus::values())],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category.in' => 'La catégorie sélectionnée est invalide.',
            'status.in' => 'Le statut sélectionné est invalide.',
            'search.max' => 'La recherche ne peut pas dépasser 255 caractères.',
        ];
    }
}
