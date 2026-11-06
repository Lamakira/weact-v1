<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use App\Enums\DateChangeReason;
use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProposeBookingDateChangeRequest extends FormRequest
{
    /**
     * L'autorisation précède la validation : un tiers avec un mauvais payload reçoit 403, pas 422.
     */
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        return $booking instanceof Booking
            && ($this->user()?->can('proposeDateChange', $booking) ?? false);
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
