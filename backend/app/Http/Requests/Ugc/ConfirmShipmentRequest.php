<?php

declare(strict_types=1);

namespace App\Http\Requests\Ugc;

use App\Models\Producer;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->userable_type === Producer::class;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'transporteur' => ['required', 'string', 'max:100'],
            'numero_suivi' => ['required', 'string', 'max:100'],
            'note_envoi' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'transporteur.required' => 'Le transporteur est obligatoire.',
            'transporteur.max' => 'Le transporteur ne peut pas dépasser 100 caractères.',
            'numero_suivi.required' => 'Le numéro de suivi est obligatoire.',
            'numero_suivi.max' => 'Le numéro de suivi ne peut pas dépasser 100 caractères.',
            'note_envoi.max' => 'La note ne peut pas dépasser 500 caractères.',
        ];
    }
}
