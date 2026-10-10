<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Services\Auth\GoogleOAuthService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query of GET /auth/google/redirect.
 *
 * `nonce` is generated and kept by the SPA (sessionStorage): it binds the whole
 * flow to the browser that started it — see GoogleOAuthService::issueState.
 */
class GoogleRedirectRequest extends FormRequest
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
            'intent' => ['required', 'string', 'in:'.implode(',', GoogleOAuthService::INTENTS)],
            'redirect' => ['nullable', 'string', 'max:2048'],
            'nonce' => ['required', 'string', 'min:43', 'max:128', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'intent.required' => 'Point d\'entrée invalide.',
            'intent.in' => 'Point d\'entrée invalide.',
            'nonce.required' => 'Session de connexion invalide. Rechargez la page et réessayez.',
            'nonce.min' => 'Session de connexion invalide. Rechargez la page et réessayez.',
            'nonce.max' => 'Session de connexion invalide. Rechargez la page et réessayez.',
            'nonce.regex' => 'Session de connexion invalide. Rechargez la page et réessayez.',
        ];
    }
}
