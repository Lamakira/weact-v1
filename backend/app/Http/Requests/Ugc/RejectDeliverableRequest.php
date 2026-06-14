<?php

declare(strict_types=1);

namespace App\Http\Requests\Ugc;

use App\Models\Producer;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtre « est un Producteur » + motif requis pour rejeter OU demander une
 * retouche sur un livrable UGC (4.3) — un seul FormRequest sert les deux actions
 * (D-4.3.i). La propriété du deal est vérifiée par Gate::authorize('review', ...)
 * dans le contrôleur. `min:5` évite un motif vide/« ok » ; `max:2000` défensif.
 */
class RejectDeliverableRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->userable_type === Producer::class
            && $user->userable_id !== null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'review_note' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'review_note.required' => 'Un motif est requis pour rejeter ou demander une retouche.',
            'review_note.min' => 'Le motif doit comporter au moins 5 caractères.',
            'review_note.max' => 'Le motif ne peut pas dépasser 2000 caractères.',
        ];
    }
}
