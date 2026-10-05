<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\RejectsWhenRegistrationDisabled;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Finalisation screen for an account created through Google.
 *
 * Google returns neither a role, nor a date of birth, nor consent, so every
 * Google-created account passes through here — which is also what keeps the 16+
 * legal gate and the CGU acceptance in place on that path.
 */
class CompleteGoogleRegistrationRequest extends FormRequest
{
    use RejectsWhenRegistrationDisabled;

    public function authorize(): bool
    {
        return (bool) config('app.registration_enabled', true);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pending_token' => ['required', 'string'],
            'role' => ['required', 'string', 'in:face,producer'],
            'accept_cgu' => ['required', 'accepted'],

            // Face branch — same rules as RegisterFaceRequest, minus email/password.
            'nom' => ['required_if:role,face', 'nullable', 'string', 'max:255'],
            'prenom' => ['required_if:role,face', 'nullable', 'string', 'max:255'],
            'date_naissance' => [
                'required_if:role,face',
                'nullable',
                'date',
                'before_or_equal:'.now()->subYears(16)->format('Y-m-d'),
            ],

            // Producer branch — same rules as RegisterProducerRequest.
            'type' => ['required_if:role,producer', 'nullable', 'string', 'in:agency,particulier'],
            'agency_name' => ['required_if:type,agency', 'nullable', 'string', 'max:100'],
            'nom_complet' => ['required_if:type,particulier', 'nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pending_token.required' => 'Session expirée. Reprenez la connexion avec Google.',
            'role.required' => 'Veuillez choisir un type de compte',
            'role.in' => 'Type de compte invalide',
            'nom.required_if' => 'Le nom est obligatoire',
            'prenom.required_if' => 'Le prénom est obligatoire',
            'date_naissance.required_if' => 'La date de naissance est obligatoire.',
            'date_naissance.date' => 'La date de naissance doit être une date valide.',
            'date_naissance.before_or_equal' => 'Vous devez avoir au moins 16 ans pour vous inscrire.',
            'type.required_if' => 'Veuillez choisir un type de compte',
            'type.in' => 'Type de compte invalide',
            'agency_name.required_if' => 'Le nom de l\'agence est obligatoire',
            'nom_complet.required_if' => 'Votre nom complet est obligatoire',
            'agency_name.max' => 'Le nom de l\'agence ne peut pas dépasser 100 caractères',
            'nom_complet.max' => 'Le nom complet ne peut pas dépasser 100 caractères',
            'accept_cgu.required' => 'Vous devez avoir 16 ans ou plus et accepter les CGU et la Politique de Confidentialité.',
            'accept_cgu.accepted' => 'Vous devez avoir 16 ans ou plus et accepter les CGU et la Politique de Confidentialité.',
        ];
    }
}
