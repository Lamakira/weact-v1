<?php

declare(strict_types=1);

namespace App\Http\Requests\Mission;

use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Models\Mission;
use App\Models\Producer;
use Illuminate\Foundation\Http\FormRequest;

class ReopenMissionRequest extends FormRequest
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
     * Reopen requests have no body to validate.
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
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $mission = $this->route('mission');

            if (! $mission instanceof Mission) {
                return;
            }

            if (
                $mission->status === MissionStatus::PendingPayment
                || ($mission->payment && $mission->payment->status === MissionPaymentStatus::Pending)
            ) {
                $validator->errors()->add(
                    'status',
                    'Cette mission a un paiement en cours et ne peut pas être réouverte'
                );

                return;
            }

            // Missions with a paid payment cannot be reopened (funds are in escrow)
            if ($mission->payment && $mission->payment->status === MissionPaymentStatus::Paid) {
                $validator->errors()->add(
                    'status',
                    'Cette mission a été payée et ne peut pas être réouverte'
                );

                return;
            }

            // Only closed missions can be reopened
            if ($mission->status === MissionStatus::Draft) {
                $validator->errors()->add(
                    'status',
                    'Seules les missions clôturées peuvent être réouvertes'
                );

                return;
            }

            if ($mission->status === MissionStatus::Published) {
                $validator->errors()->add(
                    'status',
                    'Cette mission est déjà publiée'
                );

                return;
            }

            if ($mission->status === MissionStatus::Completed) {
                $validator->errors()->add(
                    'status',
                    'Cette mission est terminée et ne peut pas être réouverte'
                );

                return;
            }
        });
    }
}
