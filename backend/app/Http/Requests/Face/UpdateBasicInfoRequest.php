<?php

declare(strict_types=1);

namespace App\Http\Requests\Face;

use App\Models\Face;
use Illuminate\Foundation\Http\FormRequest;
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
                'max:50',
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
            'username.max' => "Le nom d'utilisateur ne peut pas dépasser 50 caractères",
            'username.unique' => "Ce nom d'utilisateur est déjà pris",
        ];
    }
}
