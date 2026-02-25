<?php

declare(strict_types=1);

namespace App\Http\Requests\Face;

use App\Models\Face;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLanguesRequest extends FormRequest
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
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (is_array($this->langues)) {
            $this->merge([
                'langues' => array_map(
                    fn ($item) => is_string($item) ? trim($item) : $item,
                    $this->langues
                ),
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
        return [
            'langues' => ['nullable', 'array', 'max:10'],
            'langues.*' => ['required', 'string', 'max:50', 'distinct:strict'],
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
            'langues.max' => 'Vous ne pouvez pas ajouter plus de 10 langues.',
            'langues.*.required' => 'Chaque langue doit être renseignée.',
            'langues.*.string' => 'Chaque langue doit être une chaîne de caractères.',
            'langues.*.max' => 'Chaque langue ne peut pas dépasser 50 caractères.',
            'langues.*.distinct' => 'Chaque langue doit être unique.',
        ];
    }
}
