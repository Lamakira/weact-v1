<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ArticleCategory;
use App\Models\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateArticleCategoryRequest extends FormRequest
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
            'category' => ['required', Rule::in(ArticleCategory::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category.required' => 'La catégorie est obligatoire.',
            'category.in' => 'La catégorie sélectionnée est invalide.',
        ];
    }
}
