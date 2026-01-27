<?php

declare(strict_types=1);

namespace App\Http\Requests\Mission;

use App\Enums\MissionGender;
use App\Enums\MissionType;
use Illuminate\Validation\Rule;

/**
 * Shared validation rules and messages for Mission form requests.
 * Used by both StoreMissionRequest and UpdateMissionRequest.
 */
trait MissionValidationRules
{
    /**
     * Prepare the data for validation.
     * Set default value for nombre_faces_voulu if not provided.
     */
    protected function prepareForValidation(): void
    {
        if (!$this->has('nombre_faces_voulu') || $this->nombre_faces_voulu === null) {
            $this->merge([
                'nombre_faces_voulu' => 1,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function missionRules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:10000'],
            'date_tournage' => ['required', 'date', 'after:date_limite_candidature'],
            'profil_recherche' => ['required', 'string', 'max:5000'],
            'budget' => ['required', 'integer', 'min:1'],
            'date_limite_candidature' => ['required', 'date', 'after:today'],
            'nombre_faces_voulu' => ['nullable', 'integer', 'min:1'],
            'type_mission' => ['required', Rule::in(MissionType::values())],
            'genre_voulu' => ['required', Rule::in(MissionGender::values())],
            'lieu' => ['required', 'string', 'max:150'],
            'duree' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * Get custom messages for validator errors in French.
     *
     * @return array<string, string>
     */
    protected function missionMessages(): array
    {
        return [
            'titre.required' => 'Le titre est obligatoire.',
            'titre.max' => 'Le titre ne peut pas dépasser :max caractères.',
            'description.required' => 'La description est obligatoire.',
            'description.max' => 'La description ne peut pas dépasser :max caractères.',
            'date_tournage.required' => 'La date de tournage est obligatoire.',
            'date_tournage.date' => 'La date de tournage doit être une date valide.',
            'date_tournage.after' => 'La date de tournage doit être après la date limite de candidature.',
            'profil_recherche.required' => 'Le profil recherché est obligatoire.',
            'profil_recherche.max' => 'Le profil recherché ne peut pas dépasser :max caractères.',
            'budget.required' => 'Le budget est obligatoire.',
            'budget.integer' => 'Le budget doit être un nombre entier.',
            'budget.min' => 'Le budget doit être un nombre positif.',
            'date_limite_candidature.required' => 'La date limite de candidature est obligatoire.',
            'date_limite_candidature.date' => 'La date limite doit être une date valide.',
            'date_limite_candidature.after' => 'La date limite doit être dans le futur.',
            'nombre_faces_voulu.integer' => 'Le nombre de Faces doit être un nombre entier.',
            'nombre_faces_voulu.min' => 'Le nombre de Faces doit être au moins 1.',
            'type_mission.required' => 'Le type de mission est obligatoire.',
            'type_mission.in' => 'Le type de mission sélectionné est invalide.',
            'genre_voulu.required' => 'Le genre recherché est obligatoire.',
            'genre_voulu.in' => 'Le genre sélectionné est invalide.',
            'lieu.required' => 'Le lieu est obligatoire.',
            'lieu.max' => 'Le lieu ne peut pas dépasser :max caractères.',
            'duree.required' => 'La durée est obligatoire.',
            'duree.max' => 'La durée ne peut pas dépasser :max caractères.',
        ];
    }
}
