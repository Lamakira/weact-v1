<?php

declare(strict_types=1);

namespace App\Http\Requests\Producer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for sending a message in a conversation.
 *
 * Validates the message content field.
 */
class SendMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by ConversationPolicy in the controller
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:5000'],
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
            'content.required' => 'Le message ne peut pas être vide.',
            'content.string' => 'Le message doit être du texte.',
            'content.max' => 'Le message ne peut pas dépasser 5000 caractères.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Trim whitespace from content if it's a string
        $content = $this->input('content');
        if (is_string($content)) {
            $this->merge([
                'content' => trim($content),
            ]);
        }
    }
}
