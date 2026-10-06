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

        // Same rule as the Face field (Face\UpdatePersonalInfoRequest). Absent key =
        // untouched, so the name forms that omit it never wipe the number.
        $whatsapp = ['whatsapp_number' => ['sometimes', 'nullable', 'string', 'max:30']];

        // Conditional validation based on producer type
        if ($producer?->type === ProducerType::Agency) {
            return [
                'agency_name' => ['sometimes', 'required', 'string', 'max:100'],
                ...$whatsapp,
            ];
        }

        // Particulier type
        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            // Optional: a one-word name registers with an empty last_name
            // (ProducerRegistrationService::splitFullName).
            'last_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            ...$whatsapp,
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
            'last_name.max' => 'Le nom ne peut pas dépasser 100 caractères',
            'whatsapp_number.max' => 'Le numéro WhatsApp ne peut pas dépasser :max caractères.',
        ];
    }
}
