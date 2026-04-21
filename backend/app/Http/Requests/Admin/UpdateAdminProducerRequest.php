<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ProducerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateAdminProducerRequest extends FormRequest
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
        return [
            'type' => ['sometimes', new Enum(ProducerType::class)],
            'agency_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:1000'],
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
            'type' => 'Le type de producteur sélectionné est invalide.',
            'agency_name.max' => "Le nom de l'agence ne peut pas dépasser 255 caractères.",
            'first_name.max' => 'Le prénom ne peut pas dépasser 255 caractères.',
            'last_name.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'bio.max' => 'La bio ne peut pas dépasser 1000 caractères.',
        ];
    }
}
