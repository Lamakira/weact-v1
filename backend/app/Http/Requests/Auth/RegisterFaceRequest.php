<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\FaceGender;
use App\Http\Requests\Concerns\FaceUsernameRules;
use App\Http\Requests\Concerns\RejectsWhenRegistrationDisabled;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterFaceRequest extends FormRequest
{
    use FaceUsernameRules;
    use RejectsWhenRegistrationDisabled;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) config('app.registration_enabled', true);
    }

    /**
     * // LEGACY-BUNDLE (deploy window): remove after the release following 2026-10
     *
     * Trim and lowercase the optional username the old form still posts, exactly as
     * the profile endpoint does.
     */
    protected function prepareForValidation(): void
    {
        $this->normalizeUsernameInput(null);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Same cap as the profile (Face\UpdateBasicInfoRequest): a longer name could
            // never be re-submitted from the profile form, which always re-sends both.
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[A-Z])(?=.*\d).+$/',
            ],
            'date_naissance' => ['required', 'date', 'before_or_equal:'.now()->subYears(16)->format('Y-m-d')],
            'accept_cgu' => ['required', 'accepted'],

            // LEGACY-BUNDLE (deploy window): remove after the release following 2026-10
            // Fields the old signup form still posts: optional, same rules as the profile
            // endpoints (UpdatePersonalInfoRequest, FaceUsernameRules).
            'username' => $this->usernameRules(null),
            'sexe' => ['nullable', Rule::enum(FaceGender::class)],
            'nationalite' => ['nullable', 'string', 'max:100'],
            'pays' => ['nullable', 'string', 'max:100'],
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
            'nom.required' => 'Le nom est obligatoire',
            'nom.max' => 'Le nom ne peut pas dépasser 100 caractères',
            'prenom.required' => 'Le prénom est obligatoire',
            'prenom.max' => 'Le prénom ne peut pas dépasser 100 caractères',
            'email.required' => 'L\'email est obligatoire',
            'email.email' => 'L\'email doit être une adresse email valide',
            'email.unique' => 'Cet email est déjà utilisé',
            'password.required' => 'Le mot de passe est obligatoire',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères',
            'password.regex' => 'Le mot de passe doit contenir au moins une majuscule et un chiffre',
            'date_naissance.required' => 'La date de naissance est obligatoire.',
            'date_naissance.date' => 'La date de naissance doit être une date valide.',
            'date_naissance.before_or_equal' => 'Vous devez avoir au moins 16 ans pour vous inscrire.',
            'accept_cgu.required' => 'Vous devez avoir 16 ans ou plus et accepter les CGU et la Politique de Confidentialité.',
            'accept_cgu.accepted' => 'Vous devez avoir 16 ans ou plus et accepter les CGU et la Politique de Confidentialité.',
            'sexe.enum' => 'Le sexe sélectionné est invalide.',
            'nationalite.max' => 'La nationalité ne peut pas dépasser :max caractères.',
            'pays.max' => 'Le pays ne peut pas dépasser :max caractères.',
            'whatsapp_number.max' => 'Le numéro WhatsApp ne peut pas dépasser :max caractères.',
        ] + $this->usernameMessages();
    }
}
