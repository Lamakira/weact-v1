<?php

declare(strict_types=1);

namespace App\Http\Requests\Face;

use App\Http\Requests\Concerns\FaceUsernameRules;
use App\Models\Face;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBasicInfoRequest extends FormRequest
{
    use FaceUsernameRules;

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
     * trimmed and lowercased here rather than rejected on case alone. An unchanged
     * username is dropped from the input, so a legacy handle is neither
     * re-validated nor rewritten.
     */
    protected function prepareForValidation(): void
    {
        $this->normalizeUsernameInput(Face::find($this->user()?->userable_id));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nom' => ['sometimes', 'required', 'string', 'max:100'],
            'prenom' => ['sometimes', 'required', 'string', 'max:100'],
            'username' => $this->usernameRules($this->user()?->userable_id),
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
        ] + $this->usernameMessages();
    }
}
