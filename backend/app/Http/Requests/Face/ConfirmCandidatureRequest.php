<?php

declare(strict_types=1);

namespace App\Http\Requests\Face;

use App\Enums\CandidatureStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Rules\NoContactDetails;
use App\Services\ImageContract\ImageContractService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Confirmation (cash) ou reconfirmation (UGC) d'une candidature acceptée. Le contrôleur garde ses
 * contrôles (403, 400, 422) ; cette requête n'exige la signature de l'autorisation d'image que
 * lorsqu'il y a réellement un contrat à signer.
 */
class ConfirmCandidatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        if (! $this->mustSignImageContract()) {
            return [];
        }

        // Le code est vérifié dans la transaction de confirmation (ImageContractService::signForCandidature).
        return [
            'signature' => ['required', 'array'],
            'signature.name' => ['required', 'string', 'min:3', 'max:150', new NoContactDetails],
            'signature.code' => ['required', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'signature.required' => 'La signature du contrat est obligatoire pour confirmer cette participation.',
            'signature.name.required' => 'Votre nom complet est obligatoire pour signer.',
            'signature.name.min' => 'Votre nom complet doit comporter au moins :min caractères.',
            'signature.name.max' => 'Votre nom complet ne peut pas dépasser :max caractères.',
            'signature.code.required' => 'Le code reçu par e-mail est obligatoire.',
        ];
    }

    /**
     * La candidature est-elle celle de CETTE Face, acceptée, sur une mission avec conditions d'image,
     * sans contrat ? Tout autre cas (tiers, mission publiée avant l'activation, contrat existant,
     * statut inattendu) garde le comportement d'origine.
     */
    private function mustSignImageContract(): bool
    {
        if (! config('image_contracts.enabled')) {
            return false;
        }

        $candidature = $this->route('candidature');
        $user = $this->user();

        if (! $candidature instanceof Candidature
            || $user === null
            || $user->userable_type !== Face::class
            || $candidature->face_id !== $user->userable_id
            || $candidature->status !== CandidatureStatus::Accepted) {
            return false;
        }

        return app(ImageContractService::class)->candidatureNeedsSignature($candidature);
    }
}
