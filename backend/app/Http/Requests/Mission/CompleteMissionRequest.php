<?php

declare(strict_types=1);

namespace App\Http\Requests\Mission;

use App\Enums\CandidatureStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Models\Candidature;
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
                    'Seules les missions clôturées peuvent être marquées comme terminées'
                );

                return;
            }

            // Published missions cannot be completed directly
            if ($mission->status === MissionStatus::Published) {
                $validator->errors()->add(
                    'status',
                    'Seules les missions clôturées peuvent être marquées comme terminées'
                );

                return;
            }

            // Missions pending payment cannot be completed
            if ($mission->status === MissionStatus::PendingPayment) {
                $validator->errors()->add(
                    'status',
                    'Le paiement doit être confirmé avant de terminer la mission'
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

            $payment = $mission->payment;

            if (! $payment || $payment->status !== MissionPaymentStatus::Paid) {
                $validator->errors()->add(
                    'status',
                    'Le paiement doit être confirmé avant de terminer la mission'
                );

                return;
            }

            $selectedCandidatureIds = $payment->entries()->pluck('candidature_id');

            if ($selectedCandidatureIds->isEmpty()) {
                $validator->errors()->add(
                    'candidatures',
                    'Cette mission n\'a aucune candidature sélectionnée'
                );

                return;
            }

            $hasUnconfirmedSelectedFaces = Candidature::whereIn('id', $selectedCandidatureIds)
                ->where('status', CandidatureStatus::Accepted)
                ->exists();

            if ($hasUnconfirmedSelectedFaces) {
                $validator->errors()->add(
                    'candidatures',
                    'Toutes les faces sélectionnées doivent confirmer leur participation avant de terminer la mission'
                );
            }
        });
    }
}
