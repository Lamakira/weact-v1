<?php

declare(strict_types=1);

namespace App\Http\Requests\Face;

use App\Models\Face;
use Illuminate\Foundation\Http\FormRequest;

class StoreExperienceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->userable_type === Face::class;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'annee' => ['required', 'integer', 'min:1950', 'max:' . date('Y')],
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
            'titre.required' => 'Le titre est requis',
            'titre.max' => 'Le titre ne doit pas dépasser 150 caractères',
            'description.max' => 'La description ne doit pas dépasser 500 caractères',
            'annee.required' => "L'année est requise",
            'annee.integer' => "L'année doit être un nombre entier",
            'annee.min' => "L'année doit être supérieure ou égale à 1950",
            'annee.max' => "L'année ne peut pas être dans le futur",
        ];
    }
}
