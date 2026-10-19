<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use App\Models\Booking;
use App\Rules\NoContactDetails;
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
        $beforePayment = ! $booking instanceof Booking || $booking->status->isBeforePayment();

        return [
            'cancellation_reason' => array_filter([
                'nullable', 'string', 'max:1000',
                // Lu par le Producteur avant paiement : chat fermé, pas de coordonnées (lot B12).
                $beforePayment ? new NoContactDetails : null,
            ]),
        ];
    }

    public function messages(): array
    {
        return [
            'cancellation_reason.max' => 'La raison ne peut pas dépasser :max caractères.',
        ];
    }
}
