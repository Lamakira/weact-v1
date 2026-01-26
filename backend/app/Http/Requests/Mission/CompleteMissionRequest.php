<?php

declare(strict_types=1);

namespace App\Http\Requests\Mission;

use App\Enums\MissionStatus;
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

            // Draft missions cannot be completed
            if ($mission->status === MissionStatus::Draft) {
                $validator->errors()->add(
                    'status',
                    'Seules les missions publiées ou clôturées peuvent être marquées comme terminées'
                );

                return;
            }

            // Already completed missions cannot be completed again
            if ($mission->status === MissionStatus::Completed) {
                $validator->errors()->add(
                    'status',
                    'Cette mission est déjà terminée'
                );

                return;
            }

            // TODO: Uncomment when candidatures table exists (Epic 6)
            // For now, allow completion without candidature check
            // $hasAcceptedCandidatures = $mission->candidatures()
            //     ->whereIn('status', [
            //         CandidatureStatus::Accepted,
            //         CandidatureStatus::Confirmed,
            //         CandidatureStatus::InProgress,
            //         CandidatureStatus::Completed,
            //     ])
            //     ->exists();
            //
            // if (!$hasAcceptedCandidatures) {
            //     $validator->errors()->add(
            //         'candidatures',
            //         'Cette mission n\'a aucune candidature acceptée'
            //     );
            // }
        });
    }
}
