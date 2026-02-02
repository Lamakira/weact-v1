<?php

declare(strict_types=1);

namespace App\Http\Requests\Producer;

use App\Enums\ProducerType;
use App\Models\Producer;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBasicInfoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->userable_type === Producer::class;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $producer = Producer::find($user?->userable_id);

        // Conditional validation based on producer type
        if ($producer?->type === ProducerType::Agency) {
            return [
                'agency_name' => ['sometimes', 'required', 'string', 'max:100'],
            ];
        }

        // Particulier type
        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
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
            'agency_name.required' => "Le nom de l'agence est obligatoire",
            'agency_name.max' => "Le nom de l'agence ne peut pas dépasser 100 caractères",
            'first_name.required' => 'Le prénom est obligatoire',
            'first_name.max' => 'Le prénom ne peut pas dépasser 100 caractères',
            'last_name.required' => 'Le nom est obligatoire',
            'last_name.max' => 'Le nom ne peut pas dépasser 100 caractères',
        ];
    }
}
