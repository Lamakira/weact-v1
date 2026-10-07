<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;

class ContestBookingRequest extends FormRequest
{
    /**
     * L'autorisation précède la validation : un tiers avec un mauvais payload reçoit 403, pas 422.
     */
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        return $booking instanceof Booking
            && ($this->user()?->can('contest', $booking) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('message') && is_string($this->input('message'))) {
            $this->merge(['message' => trim($this->input('message'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'Expliquez brièvement votre contestation.',
            'message.min' => 'Votre message doit contenir au moins :min caractères.',
            'message.max' => 'Votre message ne peut pas dépasser :max caractères.',
        ];
    }
}
