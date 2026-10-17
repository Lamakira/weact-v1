<?php

declare(strict_types=1);

namespace App\Http\Requests\Face;

use Illuminate\Foundation\Http\FormRequest;

class DisputeAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Texte brut : balises retirées avant validation (jamais rendu en HTML).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('message') && is_string($this->input('message'))) {
            $this->merge(['message' => trim(strip_tags($this->input('message')))]);
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
            'message.required' => 'Expliquez en quelques mots pourquoi vous contestez (10 caractères minimum).',
            'message.min' => 'Expliquez en quelques mots pourquoi vous contestez (10 caractères minimum).',
            'message.max' => 'Votre message ne peut pas dépasser :max caractères.',
        ];
    }
}
