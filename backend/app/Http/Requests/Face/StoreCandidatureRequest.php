<?php

declare(strict_types=1);

namespace App\Http\Requests\Face;

use Illuminate\Foundation\Http\FormRequest;

class StoreCandidatureRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Only Face users can apply to missions.
     */
    public function authorize(): bool
    {
        return $this->user()?->userable_type === 'App\\Models\\Face';
    }

    /**
     * Prepare the data for validation.
     * Converts empty string motivation to null for consistency.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('message_motivation') && $this->message_motivation === '') {
            $this->merge(['message_motivation' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message_motivation' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message_motivation.max' => 'Le message de motivation ne doit pas dépasser 2000 caractères.',
        ];
    }
}
