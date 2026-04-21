<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\AdminRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateAdminRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $adminRoute = $this->route('admin');
        $adminId = is_object($adminRoute) ? $adminRoute->id : $adminRoute;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', Rule::unique('admins', 'email')->ignore($adminId)],
            'role' => ['sometimes', new Enum(AdminRole::class)],
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
            'name.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'email.email' => "L'email doit être une adresse email valide.",
            'email.unique' => 'Cet email est déjà utilisé.',
            'role' => 'Le rôle sélectionné est invalide.',
        ];
    }
}
