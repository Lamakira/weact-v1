<?php

declare(strict_types=1);

namespace App\Http\Requests\Producer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UpdateProfilePhotoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // User must be authenticated and be a Producer
        $user = $this->user();

        return $user !== null && $user->userable_type === 'App\\Models\\Producer';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                File::image()
                    ->types(['jpg', 'jpeg', 'png'])
                    ->max(5 * 1024), // 5MB in KB
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => 'Une photo est requise.',
            'photo.image' => 'Le fichier doit être une image.',
            'photo.mimes' => 'Format non supporté (JPG, PNG uniquement).',
            'photo.max' => 'Fichier trop volumineux (max 5MB).',
        ];
    }
}
