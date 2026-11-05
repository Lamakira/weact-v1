<?php

declare(strict_types=1);

namespace App\Http\Requests\ImageContract;

use App\Enums\ImageContractSupport;
use App\Enums\ImageContractTerritoire;
use App\Rules\NoContactDetails;
use App\Support\ImageContractTemplate;
use Illuminate\Validation\Rule;

/**
 * Conditions d'utilisation de l'image + signature du Producteur, partagées par la demande de
 * booking et la publication d'une mission. Le code lui-même est vérifié dans la transaction de
 * création, jamais ici.
 */
trait ImageTermsRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function imageTermsRules(): array
    {
        return [
            'image_terms' => ['required', 'array'],
            'image_terms.finalite' => ['required', 'string', 'max:200', new NoContactDetails],
            'image_terms.supports' => [
                'required',
                'array',
                'min:1',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_array($value) && in_array(ImageContractSupport::All->value, $value, true) && count($value) > 1) {
                        $fail('« Tous supports » ne se combine avec aucun autre support.');
                    }
                },
            ],
            'image_terms.supports.*' => ['string', 'distinct', Rule::in(ImageContractSupport::values())],
            'image_terms.territoire' => ['required', 'string', Rule::in(ImageContractTerritoire::values())],
            'image_terms.duree_annees' => ['required', 'integer', Rule::in(ImageContractTemplate::DUREES)],
            'signature' => ['required', 'array'],
            'signature.name' => ['required', 'string', 'min:3', 'max:150', new NoContactDetails],
            'signature.code' => ['required', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function imageTermsMessages(): array
    {
        return [
            'image_terms.required' => 'Les conditions d\'utilisation de l\'image sont obligatoires.',
            'image_terms.finalite.required' => 'La finalité de l\'utilisation de l\'image est obligatoire.',
            'image_terms.finalite.max' => 'La finalité ne peut pas dépasser :max caractères.',
            'image_terms.supports.required' => 'Choisissez au moins un support.',
            'image_terms.supports.min' => 'Choisissez au moins un support.',
            'image_terms.supports.*.in' => 'Ce support est invalide.',
            'image_terms.territoire.required' => 'Le territoire est obligatoire.',
            'image_terms.territoire.in' => 'Ce territoire est invalide.',
            'image_terms.duree_annees.required' => 'La durée d\'utilisation est obligatoire.',
            'image_terms.duree_annees.in' => 'Cette durée est invalide.',
            'signature.required' => 'La signature du contrat est obligatoire.',
            'signature.name.required' => 'Votre nom complet est obligatoire pour signer.',
            'signature.name.min' => 'Votre nom complet doit comporter au moins :min caractères.',
            'signature.name.max' => 'Votre nom complet ne peut pas dépasser :max caractères.',
            'signature.code.required' => 'Le code reçu par e-mail est obligatoire.',
        ];
    }
}
