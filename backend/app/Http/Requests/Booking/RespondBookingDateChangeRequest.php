<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;

class RespondBookingDateChangeRequest extends FormRequest
{
    /**
     * L'autorisation précède la validation : l'auteur ou un tiers reçoit 403, pas 422.
     */
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        return $booking instanceof Booking
            && ($this->user()?->can('respondDateChange', $booking) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:accept,refuse'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' => 'Indiquez si vous acceptez ou refusez la nouvelle date.',
            'decision.in' => 'La réponse doit être « accept » ou « refuse ».',
        ];
    }
}
