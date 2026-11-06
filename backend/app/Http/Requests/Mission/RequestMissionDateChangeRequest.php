<?php

declare(strict_types=1);

namespace App\Http\Requests\Mission;

use App\Enums\DateChangeReason;
use App\Models\Mission;
use App\Models\Producer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestMissionDateChangeRequest extends FormRequest
{
    /**
     * User must be Producer and own the mission.
     * Les gardes métier (payée, préavis, quota) vivent dans MissionDateChangeService, sous verrou.
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
            'new_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', Rule::enum(DateChangeReason::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'new_date.required' => 'La nouvelle date est obligatoire.',
            'new_date.date_format' => 'La nouvelle date doit être une date valide.',
            'reason.required' => 'Le motif du changement est obligatoire.',
            'reason.enum' => 'Le motif sélectionné est invalide.',
        ];
    }
}
