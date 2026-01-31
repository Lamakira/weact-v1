<?php

declare(strict_types=1);

namespace App\Http\Requests\Face;

use Illuminate\Foundation\Http\FormRequest;

class StoreRatingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via Policy
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'score' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Sanitizes comment to prevent XSS (defense-in-depth).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('comment') && $this->comment !== null) {
            $this->merge([
                'comment' => strip_tags($this->comment),
            ]);
        }
    }

    /**
     * Get custom messages for validator errors.
     *
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
