<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\FaceCategory;
use App\Enums\FaceNiche;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateAdminFaceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
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
        $faceId = $this->route('face')?->id;

        return [
            'nom' => ['sometimes', 'string', 'max:255'],
            'prenom' => ['sometimes', 'string', 'max:255'],
            'username' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('faces', 'username')->ignore($faceId),
            ],
            'bio' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'ville' => ['sometimes', 'nullable', 'string', 'max:255'],
            'quartier' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pays' => ['sometimes', 'nullable', 'string', 'max:255'],
            'categories' => ['sometimes', 'nullable', 'array'],
            'categories.*' => [new Enum(FaceCategory::class)],
            'niches' => ['sometimes', 'nullable', 'array'],
            'niches.*' => [new Enum(FaceNiche::class)],
            'is_available' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nom.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'prenom.max' => 'Le prénom ne peut pas dépasser 255 caractères.',
            'username.max' => "Le nom d'utilisateur ne peut pas dépasser 255 caractères.",
            'username.unique' => "Ce nom d'utilisateur est déjà utilisé.",
            'bio.max' => 'La bio ne peut pas dépasser 1000 caractères.',
            'ville.max' => 'La ville ne peut pas dépasser 255 caractères.',
            'quartier.max' => 'Le quartier ne peut pas dépasser 255 caractères.',
            'pays.max' => 'Le pays ne peut pas dépasser 255 caractères.',
            'categories.array' => 'Les catégories doivent être un tableau.',
            'categories.*.Illuminate\Validation\Rules\Enum' => 'La catégorie sélectionnée est invalide.',
            'niches.array' => 'Les niches doivent être un tableau.',
            'niches.*.Illuminate\Validation\Rules\Enum' => 'La niche sélectionnée est invalide.',
            'is_available.boolean' => 'Le statut de disponibilité doit être vrai ou faux.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
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
