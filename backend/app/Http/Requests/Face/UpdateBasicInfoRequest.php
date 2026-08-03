<?php

declare(strict_types=1);

namespace App\Http\Requests\Face;

use App\Models\Face;
use App\Services\Auth\UsernameGenerator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateBasicInfoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->userable_type === Face::class;
    }

    /**
     * Normalize the username before validation.
     *
     * `username` is the public profile URL segment (`/faces/{username}`), so it is
     * trimmed and lowercased here rather than rejected on case alone.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('username') && is_string($this->input('username'))) {
            $this->merge([
                'username' => Str::lower(trim($this->input('username'))),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $faceId = $user?->userable_id;

        return [
            'nom' => ['sometimes', 'required', 'string', 'max:100'],
            'prenom' => ['sometimes', 'required', 'string', 'max:100'],
            'username' => [
                'sometimes',
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-z0-9_-]+$/',
                Rule::notIn(UsernameGenerator::RESERVED),
                Rule::unique('faces', 'username')->ignore($faceId),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom est obligatoire',
            'nom.max' => 'Le nom ne peut pas dépasser 100 caractères',
            'prenom.required' => 'Le prénom est obligatoire',
            'prenom.max' => 'Le prénom ne peut pas dépasser 100 caractères',
            'username.required' => "Le nom d'utilisateur est obligatoire",
            'username.min' => "Le nom d'utilisateur doit contenir au moins 3 caractères",
            'username.max' => "Le nom d'utilisateur ne peut pas dépasser 50 caractères",
            'username.regex' => "Le nom d'utilisateur ne peut contenir que des lettres, chiffres, tirets et underscores",
            'username.not_in' => "Ce nom d'utilisateur est réservé",
            'username.unique' => "Ce nom d'utilisateur est déjà pris",
        ];
    }
}
