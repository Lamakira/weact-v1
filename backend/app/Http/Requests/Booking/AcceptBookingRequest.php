<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use App\Models\Booking;
use App\Rules\NoContactDetails;
use Illuminate\Foundation\Http\FormRequest;

class AcceptBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        if (! $this->mustSignImageContract()) {
            return [];
        }

        // Autorisation d'image : la Face signe en acceptant. Le code est vérifié dans la
        // transaction d'acceptation (BookingService::accept).
        return [
            'signature' => ['required', 'array'],
            'signature.name' => ['required', 'string', 'min:3', 'max:150', new NoContactDetails],
            'signature.code' => ['required', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'signature.required' => 'La signature du contrat est obligatoire pour accepter ce booking.',
            'signature.name.required' => 'Votre nom complet est obligatoire pour signer.',
            'signature.name.min' => 'Votre nom complet doit comporter au moins :min caractères.',
            'signature.name.max' => 'Votre nom complet ne peut pas dépasser :max caractères.',
            'signature.code.required' => 'Le code reçu par e-mail est obligatoire.',
        ];
    }

    /**
     * Le booking porte-t-il un contrat que CETTE Face n'a pas encore signé ? Un tiers ou un
     * booking sans contrat (créé avant l'activation) garde le comportement d'origine.
     */
    private function mustSignImageContract(): bool
    {
        if (! config('image_contracts.enabled')) {
            return false;
        }

        $booking = $this->route('booking');
        $user = $this->user();

        if (! $booking instanceof Booking || $user === null || $user->id !== $booking->face_id) {
            return false;
        }

        $contract = $booking->imageContract;

        return $contract !== null && ! $contract->isSignedByFace();
    }
}
