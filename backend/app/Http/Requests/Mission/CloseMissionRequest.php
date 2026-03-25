<?php

declare(strict_types=1);

namespace App\Http\Requests\Mission;

use App\Enums\MissionStatus;
use App\Models\Mission;
use App\Models\Producer;
use Illuminate\Foundation\Http\FormRequest;

class CloseMissionRequest extends FormRequest
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
     * Close requests have no body to validate.
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
            /** @var Mission $mission */
            $mission = $this->route('mission');

            // Only published missions can be closed
            if ($mission->status === MissionStatus::Draft) {
                $validator->errors()->add(
                    'status',
                    'Seules les missions publiées peuvent être clôturées'
                );

                return;
            }

            if ($mission->status === MissionStatus::Closed) {
                $validator->errors()->add(
                    'status',
                    'Cette mission est déjà clôturée'
                );

                return;
            }

            if ($mission->status === MissionStatus::Completed) {
                $validator->errors()->add(
                    'status',
                    'Cette mission est déjà terminée'
                );

                return;
            }

            if ($mission->status === MissionStatus::PendingPayment) {
                $validator->errors()->add(
                    'status',
                    'Une mission en attente de paiement ne peut pas être clôturée manuellement'
                );

                return;
            }

            if ($mission->date_tournage !== null && $mission->date_tournage->isBefore(now()->startOfDay())) {
                $validator->errors()->add(
                    'status',
                    'Une mission dont la date de tournage est passée ne peut pas être clôturée manuellement'
                );
            }
        });
    }
}
