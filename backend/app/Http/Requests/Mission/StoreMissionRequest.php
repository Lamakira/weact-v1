<?php

declare(strict_types=1);

namespace App\Http\Requests\Mission;

use App\Enums\CompensationType;
use App\Enums\MissionType;
use App\Models\Producer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMissionRequest extends FormRequest
{
    use MissionValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     * Only Producers can create missions.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->userable_type === Producer::class;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * For UGC missions the cash `budget` is dropped (derived server-side per
     * D-1.3.b) and the dotation rules are layered on top of the standard mission
     * rules. The shared MissionValidationRules trait is intentionally left
     * untouched (it also serves UpdateMissionRequest).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = $this->missionRules();

        if ($this->input('type_mission') === MissionType::Ugc->value) {
            unset($rules['budget']); // dérivé serveur (montant_remuneration / 0) — D-1.3.b

            // Une mission UGC (dotation) n'a ni date, ni durée, ni lieu de tournage → on relâche
            // ces champs en nullable POUR L'UGC (le trait partagé MissionValidationRules reste strict).
            $rules['date_tournage'] = ['nullable', 'date', 'after:date_limite_candidature'];
            $rules['lieu'] = ['nullable', 'string', 'max:150'];
            $rules['duree'] = ['nullable', 'string', 'max:100'];

            $rules['type_compensation'] = ['required', Rule::in(CompensationType::values())];
            $rules['nom_produit'] = ['required', 'string', 'max:255'];
            $rules['valeur_produit'] = ['required', 'integer', 'min:1', 'max:4294967295'];

            if ($this->input('type_compensation') === CompensationType::Hybrid->value) {
                $rules['nombre_videos'] = ['required', 'integer', 'min:2', 'max:20']; // était min:1 (ugc-4-0, option B)
                $rules['montant_remuneration'] = ['required', 'integer', 'min:1', 'max:4294967295'];
            }
            // produit seul : nombre_videos forcé serveur à 2 (MissionService), montant_remuneration ignoré.
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors in French.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->missionMessages(), [
            'type_compensation.required' => 'Le type de compensation est obligatoire.',
            'type_compensation.in' => 'Le type de compensation sélectionné est invalide.',
            'nom_produit.required' => 'Le nom du produit est obligatoire.',
            'nom_produit.max' => 'Le nom du produit ne peut pas dépasser :max caractères.',
            'valeur_produit.required' => 'La valeur du produit est obligatoire.',
            'valeur_produit.integer' => 'La valeur du produit doit être un nombre entier.',
            'valeur_produit.min' => 'La valeur du produit doit être un nombre positif.',
            'valeur_produit.max' => 'La valeur du produit dépasse le maximum autorisé.',
            'nombre_videos.required' => 'Le nombre de vidéos est obligatoire.',
            'nombre_videos.integer' => 'Le nombre de vidéos doit être un nombre entier.',
            'nombre_videos.min' => 'Le nombre de vidéos doit être au moins de :min.',
            'nombre_videos.max' => 'Le nombre de vidéos ne peut pas dépasser :max.',
            'montant_remuneration.required' => 'Le montant de la rémunération est obligatoire.',
            'montant_remuneration.integer' => 'Le montant de la rémunération doit être un nombre entier.',
            'montant_remuneration.min' => 'Le montant de la rémunération doit être un nombre positif.',
            'montant_remuneration.max' => 'Le montant de la rémunération dépasse le maximum autorisé.',
        ]);
    }
}
