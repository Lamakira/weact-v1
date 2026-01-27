<?php

declare(strict_types=1);

namespace App\Http\Requests\Producer;

use App\Models\Producer;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAgencyLogoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (!$user || !$user->userable instanceof Producer) {
            return false;
        }

        // CRITICAL: Only Agency producers can upload logos
        return $user->userable->isAgency();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.required' => 'Le logo est requis.',
            'logo.image' => 'Le fichier doit être une image.',
            'logo.mimes' => 'Le logo doit être au format JPG ou PNG.',
            'logo.max' => 'Le logo ne peut pas dépasser 2 Mo.',
        ];
    }
}
