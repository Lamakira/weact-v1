<?php

declare(strict_types=1);

namespace App\Http\Requests\Face;

use App\Models\Face;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExperienceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user || $user->userable_type !== Face::class) {
            return false;
        }

        // Verify the experience belongs to the authenticated Face
        $experience = $this->route('experience');

        return $experience && $experience->face_id === $user->userable_id;
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
            'date_debut' => ['required', 'date', 'before_or_equal:today'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut', 'before_or_equal:today'],
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
            'date_debut.required' => 'La date de début est requise',
            'date_debut.date' => "La date de début n'est pas valide",
            'date_debut.before_or_equal' => 'La date de début ne peut pas être dans le futur',
            'date_fin.date' => "La date de fin n'est pas valide",
            'date_fin.after_or_equal' => 'La date de fin doit être après la date de début',
            'date_fin.before_or_equal' => 'La date de fin ne peut pas être dans le futur',
        ];
    }
}
