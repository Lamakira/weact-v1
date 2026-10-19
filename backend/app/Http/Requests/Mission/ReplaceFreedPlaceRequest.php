<?php

declare(strict_types=1);

namespace App\Http\Requests\Mission;

use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/** Lot B4 : le Producer propriétaire donne une place libérée à une candidature non retenue. */
class ReplaceFreedPlaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User || $user->userable_type !== Producer::class) {
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
            'candidature_id' => ['required', 'string', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'candidature_id.required' => 'Choisissez la candidature à qui proposer la place.',
            'candidature_id.uuid' => 'Candidature invalide.',
        ];
    }
}
