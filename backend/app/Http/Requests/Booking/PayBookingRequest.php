<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class PayBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->id === $this->route('booking')->producer_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_mode' => ['required', 'string', 'in:mtn_open,moov,celtiis'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_mode.required' => 'Le mode de paiement est obligatoire.',
            'payment_mode.in' => 'Le mode de paiement doit être MTN, Moov ou Celtiis.',
        ];
    }
}
