<?php

declare(strict_types=1);

namespace App\Http\Requests\Face;

use App\Models\Face;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePhysicalCharacteristicsRequest extends FormRequest
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
            'taille' => [
                'bail',
                'nullable',
                'integer',
                function ($attribute, $value, $fail) {
                    if ($value !== null && $value < 0) {
                        $fail('La valeur doit être positive');
                    }
                },
                'min:50',
                'max:300',
            ],
            'poids' => [
                'bail',
                'nullable',
                'integer',
                function ($attribute, $value, $fail) {
                    if ($value !== null && $value < 0) {
                        $fail('La valeur doit être positive');
                    }
                },
                'min:20',
                'max:500',
            ],
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
            'taille.integer' => 'La taille doit être un nombre entier',
            'taille.min' => 'La taille doit être entre 50 et 300 cm',
            'taille.max' => 'La taille doit être entre 50 et 300 cm',
            'poids.integer' => 'Le poids doit être un nombre entier',
            'poids.min' => 'Le poids doit être entre 20 et 500 kg',
            'poids.max' => 'Le poids doit être entre 20 et 500 kg',
        ];
    }
}
