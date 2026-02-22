<?php

declare(strict_types=1);

namespace App\Http\Requests\Face;

use App\Models\Face;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTarifsRequest extends FormRequest
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
        return [
            'tarif_horaire' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'tarif_journalier' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Contracts\Validation\Validator $validator) {
            $tarifHoraire = $this->input('tarif_horaire');
            $tarifJournalier = $this->input('tarif_journalier');

            // When both are provided and non-null, daily should be >= half-day
            if ($tarifHoraire !== null && $tarifJournalier !== null
                && is_numeric($tarifHoraire) && is_numeric($tarifJournalier)
                && (int) $tarifJournalier < (int) $tarifHoraire) {
                $validator->errors()->add(
                    'tarif_journalier',
                    'Le tarif journalier doit être supérieur ou égal au tarif demi-journée'
                );
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tarif_horaire.integer' => 'Le tarif demi-journée doit être un nombre entier',
            'tarif_horaire.min' => 'Le tarif demi-journée doit être positif',
            'tarif_horaire.max' => 'Le tarif demi-journée ne peut pas dépasser 10 000 000 XOF',
            'tarif_journalier.integer' => 'Le tarif journalier doit être un nombre entier',
            'tarif_journalier.min' => 'Le tarif journalier doit être positif',
            'tarif_journalier.max' => 'Le tarif journalier ne peut pas dépasser 100 000 000 XOF',
        ];
    }
}
