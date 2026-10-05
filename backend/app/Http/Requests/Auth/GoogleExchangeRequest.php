<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Body of POST /auth/google/exchange: the one-shot code from the callback URL,
 * plus the browser-held nonce the flow was started with.
 */
class GoogleExchangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
            'nonce' => ['required', 'string'],
        ];
    }
}
