<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Règles communes aux envois de message : un texte OU une position instantanée.
 * Si `location` est présent, le `content` envoyé est ignoré (les contrôleurs n'utilisent que la position).
 */
trait ValidatesMessageLocation
{
    /**
     * @return array<string, array<int, string>>
     */
    protected function locationRules(): array
    {
        return [
            'location' => ['nullable', 'array'],
            'location.lat' => ['required_with:location', 'numeric', 'between:-90,90'],
            'location.lng' => ['required_with:location', 'numeric', 'between:-180,180'],
            'location.accuracy_m' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function locationMessages(): array
    {
        return [
            'content.required_without' => 'Le message ne peut pas être vide.',
            'location.array' => 'La position est invalide.',
            'location.lat.required_with' => 'La latitude est obligatoire.',
            'location.lat.numeric' => 'La latitude doit être un nombre.',
            'location.lat.between' => 'La latitude doit être comprise entre -90 et 90.',
            'location.lng.required_with' => 'La longitude est obligatoire.',
            'location.lng.numeric' => 'La longitude doit être un nombre.',
            'location.lng.between' => 'La longitude doit être comprise entre -180 et 180.',
            'location.accuracy_m.integer' => 'La précision doit être un nombre entier.',
            'location.accuracy_m.min' => 'La précision est invalide.',
            'location.accuracy_m.max' => 'La précision est invalide.',
        ];
    }
}
