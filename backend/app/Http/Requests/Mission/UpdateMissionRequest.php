<?php

declare(strict_types=1);

namespace App\Http\Requests\Mission;

use App\Enums\MissionStatus;
use App\Models\Mission;
use App\Models\Producer;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMissionRequest extends FormRequest
{
    use MissionValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     * User must be Producer and own the mission.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (!$user || $user->userable_type !== Producer::class) {
            return false;
        }

        /** @var Mission $mission */
        $mission = $this->route('mission');

        // Check ownership
        return $user->userable_id === $mission->producer_id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->missionRules();
    }

    /**
     * Configure the validator instance.
     * Add custom validation for mission status check.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var Mission $mission */
            $mission = $this->route('mission');

            // Check if mission is editable (not closed or completed)
            if (in_array($mission->status, [MissionStatus::Closed, MissionStatus::Completed], true)) {
                $validator->errors()->add(
                    'mission',
                    'Une mission clôturée ou terminée ne peut pas être modifiée'
                );
            }
        });
    }

    /**
     * Get custom messages for validator errors in French.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->missionMessages();
    }
}
