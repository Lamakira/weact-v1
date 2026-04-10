<?php

declare(strict_types=1);

namespace App\Http\Requests\Face;

use App\Enums\FaceGender;
use App\Models\Face;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePersonalInfoRequest extends FormRequest
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
            'sexe' => ['nullable', Rule::enum(FaceGender::class)],
            'date_naissance' => ['nullable', 'date', 'before_or_equal:'.now()->subYears(16)->format('Y-m-d')],
            'nationalite' => ['nullable', 'string', 'max:100'],
            'pays' => ['nullable', 'string', 'max:100'],
            'show_age' => ['sometimes', 'boolean'],
            'whatsapp_number' => ['nullable', 'string', 'max:30'],
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
            'sexe.enum' => 'Le sexe sélectionné est invalide.',
            'date_naissance.date' => 'La date de naissance doit être une date valide.',
            'date_naissance.before_or_equal' => 'Vous devez avoir au moins 16 ans.',
            'nationalite.max' => 'La nationalité ne peut pas dépasser :max caractères.',
            'pays.max' => 'Le pays ne peut pas dépasser :max caractères.',
            'show_age.boolean' => 'La valeur doit être vrai ou faux.',
            'whatsapp_number.max' => 'Le numéro WhatsApp ne peut pas dépasser :max caractères.',
        ];
    }
}
