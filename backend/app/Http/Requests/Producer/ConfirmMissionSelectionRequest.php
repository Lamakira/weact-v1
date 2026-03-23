<?php

declare(strict_types=1);

namespace App\Http\Requests\Producer;

use App\Models\Mission;
use App\Models\Producer;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmMissionSelectionRequest extends FormRequest
{
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
            'candidature_ids' => ['required', 'array', 'min:1'],
            'candidature_ids.*' => ['required', 'integer', 'exists:candidatures,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'candidature_ids.required' => 'Vous devez sélectionner au moins une candidature.',
            'candidature_ids.min' => 'Vous devez sélectionner au moins une candidature.',
            'candidature_ids.*.exists' => 'Une ou plusieurs candidatures sélectionnées sont invalides.',
        ];
    }
}
