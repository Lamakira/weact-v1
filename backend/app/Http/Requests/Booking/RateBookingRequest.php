<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class RateBookingRequest extends FormRequest
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
            'score' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('comment') && $this->comment !== null) {
            $this->merge([
                'comment' => strip_tags($this->comment),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'score.required' => 'La note est obligatoire',
            'score.integer' => 'La note doit être un nombre entier',
            'score.min' => 'La note minimum est 1 étoile',
            'score.max' => 'La note maximum est 5 étoiles',
            'comment.max' => 'Le commentaire ne peut pas dépasser 1000 caractères',
        ];
    }
}
