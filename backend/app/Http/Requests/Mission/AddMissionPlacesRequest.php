<?php

declare(strict_types=1);

namespace App\Http\Requests\Mission;

use App\Models\Mission;
use App\Models\Producer;
use Illuminate\Foundation\Http\FormRequest;

class AddMissionPlacesRequest extends FormRequest
{
    /**
     * User must be Producer and own the mission.
     * La garde métier (canAddPlaces) vit dans MissionService::addPlaces, sous verrou.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user || $user->userable_type !== Producer::class) {
            return false;
        }

        /** @var Mission $mission */
        $mission = $this->route('mission');

        return $user->userable_id === $mission->producer_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'places' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'places.required' => 'Indiquez le nombre de places à ajouter.',
            'places.integer' => 'Le nombre de places doit être un nombre entier.',
            'places.min' => 'Vous devez ajouter au moins une place.',
            'places.max' => 'Vous pouvez ajouter 20 places au maximum à la fois.',
        ];
    }
}
