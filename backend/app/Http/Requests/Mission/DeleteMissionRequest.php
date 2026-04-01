<?php

declare(strict_types=1);

namespace App\Http\Requests\Mission;

use App\Enums\MissionStatus;
use App\Models\Mission;
use App\Models\Producer;
use Illuminate\Foundation\Http\FormRequest;

class DeleteMissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * User must be Producer and own the mission.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user || $user->userable_type !== Producer::class) {
            return false;
        }

        /** @var Mission $mission */
        $mission = $this->route('mission');

        // Check ownership
        return $user->userable_id === $mission->producer_id;
    }

    /**
     * Get the validation rules that apply to the request.
     * Delete requests have no body to validate.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Configure the validator instance.
     * Add custom validation for mission status.
     * Active candidatures are auto-cancelled by MissionService::deleteMission().
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var Mission $mission */
            $mission = $this->route('mission');

            // Closed, pending_payment and completed missions cannot be deleted
            if (in_array($mission->status, [MissionStatus::Closed, MissionStatus::PendingPayment, MissionStatus::Completed], true)) {
                $validator->errors()->add(
                    'mission',
                    'Une mission clôturée, en attente de paiement ou terminée ne peut pas être supprimée'
                );
            }
        });
    }
}
