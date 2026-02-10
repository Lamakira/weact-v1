<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ArticleCategory;
use App\Enums\ArticleStatus;
use App\Models\Admin;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreArticleRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'min:50'],
            'category' => ['required', Rule::in(ArticleCategory::values())],
            'status' => ['sometimes', Rule::in(ArticleStatus::values())],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:500'],
            'featured_image' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Le titre est obligatoire.',
            'title.max' => 'Le titre ne peut pas dépasser :max caractères.',
            'content.required' => 'Le contenu est obligatoire.',
            'content.min' => 'Le contenu doit contenir au moins :min caractères.',
            'category.required' => 'La catégorie est obligatoire.',
            'category.in' => 'La catégorie sélectionnée est invalide.',
            'status.in' => 'Le statut sélectionné est invalide.',
            'excerpt.max' => "L'extrait ne peut pas dépasser :max caractères.",
            'featured_image.image' => "L'image doit être un fichier image.",
            'featured_image.mimes' => "L'image doit être au format JPEG ou PNG.",
            'featured_image.max' => "L'image ne peut pas dépasser 2 Mo.",
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'error' => [
                    'code' => 'validation_error',
                    'message' => 'Les données fournies ne sont pas valides',
                    'details' => $validator->errors()->toArray(),
                ],
            ], 422)
        );
    }
}
