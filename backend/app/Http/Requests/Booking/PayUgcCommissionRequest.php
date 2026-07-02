<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;

class PayUgcCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        return $booking instanceof Booking
            && (bool) $this->user()?->can('payCommission', $booking);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
