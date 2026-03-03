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
            'payment_mode' => ['required', 'string', 'in:mtn,moov,momo_test'],
            'phone_number' => ['required', 'string', 'regex:/^[0-9]{6,15}$/'],
            'phone_country' => ['required', 'string', 'in:bj,tg,ci,sn,bf'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_mode.required' => 'Le mode de paiement est obligatoire.',
            'payment_mode.in' => 'Le mode de paiement doit être MTN, Moov ou MoMo Test.',
            'phone_number.required' => 'Le numéro de téléphone est obligatoire.',
            'phone_number.regex' => 'Le numéro de téléphone doit contenir entre 6 et 15 chiffres.',
            'phone_country.required' => 'Le pays est obligatoire.',
            'phone_country.in' => 'Le pays doit être BJ, TG, CI, SN ou BF.',
        ];
    }
}
