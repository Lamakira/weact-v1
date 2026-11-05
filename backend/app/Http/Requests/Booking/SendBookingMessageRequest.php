<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use App\Http\Requests\Concerns\ValidatesMessageLocation;
use Illuminate\Foundation\Http\FormRequest;

class SendBookingMessageRequest extends FormRequest
{
    use ValidatesMessageLocation;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'content' => ['required_without:location', 'nullable', 'string', 'min:1', 'max:2000'],
            ...$this->locationRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'content.max' => 'Le message ne peut pas dépasser 2000 caractères.',
            ...$this->locationMessages(),
        ];
    }
}
