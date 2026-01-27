<?php

declare(strict_types=1);

namespace App\Http\Requests\Face;

use App\Models\Face;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBioLocationRequest extends FormRequest
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
            'bio' => ['nullable', 'string', 'max:500'],
            'ville' => ['nullable', 'string', 'max:100'],
            'quartier' => ['nullable', 'string', 'max:100'],
            'pays' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            $quartier = $this->input('quartier');
            $ville = $this->input('ville');

            // If quartier is provided, ville must also be provided
            if (!empty($quartier) && empty($ville)) {
                $validator->errors()->add(
                    'quartier',
                    'La ville est requise pour enregistrer le quartier'
                );
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bio.max' => 'La bio ne peut pas dépasser 500 caractères',
            'ville.max' => 'La ville ne peut pas dépasser 100 caractères',
            'quartier.max' => 'Le quartier ne peut pas dépasser 100 caractères',
            'pays.max' => 'Le pays ne peut pas dépasser 100 caractères',
        ];
    }
}
