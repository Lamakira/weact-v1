<?php

declare(strict_types=1);

namespace App\Http\Requests\Face;

use App\Enums\FaceGender;
use App\Models\Face;
use App\Rules\InTerritoryList;
use App\Support\Territories;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        $face = $this->user()?->userable;

        return [
            'sexe' => ['nullable', Rule::enum(FaceGender::class)],
            'date_naissance' => ['nullable', 'date', 'before_or_equal:'.now()->subYears(16)->format('Y-m-d')],
            'nationalite' => ['nullable', 'string', 'max:100', new InTerritoryList(Territories::nationalities(), $face instanceof Face ? $face->nationalite : null)],
            'pays' => ['nullable', 'string', 'max:100', new InTerritoryList(Territories::countries(), $face instanceof Face ? $face->pays : null)],
            'show_age' => ['sometimes', 'boolean'],
            'whatsapp_number' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * Autorisation d'image : une Face mineure ne peut pas se rendre majeure (ni effacer sa date)
     * pour signer seule ; la correction passe par le support.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! config('image_contracts.enabled') || $validator->errors()->has('date_naissance')) {
                return;
            }

            $face = $this->user()?->userable;
            if (! $face instanceof Face || ! $face->isMinor() || ! $this->has('date_naissance')) {
                return;
            }

            $new = $this->input('date_naissance');
            $staysMinor = filled($new) && \Carbon\Carbon::parse((string) $new)->age < 18;

            if (! $staysMinor) {
                $validator->errors()->add('date_naissance', 'Contactez le support pour corriger votre date de naissance.');
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
