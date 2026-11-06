<?php

declare(strict_types=1);

namespace App\Http\Requests\Face;

use App\Models\Candidature;
use App\Models\Face;
use Illuminate\Foundation\Http\FormRequest;

/** Réponse d'une Face à un changement de date de mission : confirmer ou se retirer. */
class RespondDateChangeRequest extends FormRequest
{
    /**
     * Face propriétaire de la candidature.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $candidature = $this->route('candidature');

        return $user !== null
            && $user->userable_type === Face::class
            && $candidature instanceof Candidature
            && $candidature->face_id === $user->userable_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:confirm,withdraw'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' => 'Indiquez si vous confirmez ou si vous vous retirez.',
            'decision.in' => 'La réponse doit être « confirm » ou « withdraw ».',
        ];
    }
}
