<?php

declare(strict_types=1);

namespace App\Http\Requests\Mission;

use App\Enums\MissionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FilterMissionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * All authenticated users can filter missions.
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
        return [
            'lieu' => ['sometimes', 'nullable', 'string', 'max:255'],
            'budget_min' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'budget_max' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'date_tournage' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'type_mission' => ['sometimes', 'nullable', Rule::enum(MissionType::class)],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Get custom messages for validator errors in French.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lieu.string' => 'Le lieu doit être une chaîne de caractères.',
            'lieu.max' => 'Le lieu ne peut pas dépasser 255 caractères.',
            'budget_min.integer' => 'Le budget minimum doit être un nombre entier.',
            'budget_min.min' => 'Le budget minimum doit être positif.',
            'budget_max.integer' => 'Le budget maximum doit être un nombre entier.',
            'budget_max.min' => 'Le budget maximum doit être positif.',
            'date_tournage.date_format' => 'La date de tournage doit être au format AAAA-MM-JJ.',
            'type_mission.enum' => 'Le type de mission sélectionné est invalide.',
            'page.integer' => 'Le numéro de page doit être un nombre entier.',
            'page.min' => 'Le numéro de page doit être au minimum 1.',
        ];
    }

    /**
     * Configure the validator instance.
     * Add custom validation for budget_max >= budget_min when both are provided.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $budgetMin = $this->input('budget_min');
                $budgetMax = $this->input('budget_max');

                if ($budgetMin !== null && $budgetMax !== null && (int) $budgetMax < (int) $budgetMin) {
                    $validator->errors()->add(
                        'budget_max',
                        'Le budget maximum doit être supérieur ou égal au budget minimum.'
                    );
                }
            },
        ];
    }
}
