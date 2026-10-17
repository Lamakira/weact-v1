<?php

declare(strict_types=1);

namespace App\Http\Requests\Mission;

use App\Models\Mission;
use App\Models\Producer;
use Illuminate\Foundation\Http\FormRequest;

class CompleteMissionRequest extends FormRequest
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
     * Complete requests have no body to validate.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Configure the validator instance.
     * Add custom validation for mission status and candidature check.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var Mission $mission */
            $mission = $this->route('mission');

            // Single source of truth shared with MissionResource::can_complete.
            $blocker = $mission->completionBlocker();

            if ($blocker !== null) {
                $validator->errors()->add($blocker[0], $blocker[1]);
            }
        });
    }
}
