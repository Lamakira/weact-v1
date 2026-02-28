<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use App\Enums\BookingStatus;
use Illuminate\Foundation\Http\FormRequest;

class RefuseBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $booking = $this->route('booking');

        $rules = [
            'cancellation_reason' => ['nullable', 'string', 'max:1000'],
        ];

        // Reason is mandatory if the booking has already been paid
        if ($booking && $booking->status === BookingStatus::Paid) {
            $rules['cancellation_reason'] = ['required', 'string', 'max:1000'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'Veuillez indiquer la raison du refus pour un booking déjà payé.',
            'cancellation_reason.max' => 'La raison ne peut pas dépasser :max caractères.',
        ];
    }
}
