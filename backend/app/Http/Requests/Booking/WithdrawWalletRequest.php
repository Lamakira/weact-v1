<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled by 'face' middleware on route
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $balance = (int) ($this->user()->balance ?? 0);

        return [
            'amount'        => ['required', 'integer', 'min:1', "max:{$balance}"],
            'payment_mode'  => ['required', 'string', 'in:mtn,moov,momo_test'],
            'phone_number'  => ['required', 'string', 'regex:/^[0-9]{6,15}$/'],
            'phone_country' => ['required', 'string', 'in:bj,tg,ci,sn,bf'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $balance = (int) ($this->user()->balance ?? 0);
        $formatted = number_format($balance, 0, ',', ' ');

        return [
            'amount.max' => "Solde insuffisant (solde disponible: {$formatted} XOF)",
            'amount.min' => 'Le montant doit être supérieur à 0',
        ];
    }
}
