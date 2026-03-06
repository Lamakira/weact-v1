<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use App\Enums\BookingCancellationReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CancelBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cancellation_reason' => [
                'required',
                'string',
                Rule::enum(BookingCancellationReason::class),
            ],
        ];
    }
}
