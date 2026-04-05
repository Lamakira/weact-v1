<?php

declare(strict_types=1);

namespace App\Http\Requests\Face;

use App\Constants\BeninCities;
use App\Models\Face;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBioLocationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('pays')) {
            return;
        }

        $pays = $this->input('pays');
        $normalizedPays = is_string($pays) ? trim($pays) : $pays;
        $payload = [];

        if ($normalizedPays !== $pays) {
            $payload['pays'] = $normalizedPays;
        }

        if ($normalizedPays !== 'Bénin' && ! $this->has('ville')) {
            $payload['ville'] = null;
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

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
            'bio' => ['nullable', 'string', 'max:500'],
            'ville' => ['nullable', 'string', 'max:100', Rule::in(BeninCities::values())],
            'pays' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $ville = $this->input('ville');
            $effectivePays = $this->has('pays')
                ? $this->input('pays')
                : $this->user()?->userable?->pays;

            if (! empty($ville) && $effectivePays !== 'Bénin') {
                $validator->errors()->add(
                    'ville',
                    'La ville doit être vide lorsque le pays n\'est pas "Bénin".'
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
            'bio.max' => 'La bio ne peut pas dépasser 500 caractères',
            'ville.max' => 'La ville ne peut pas dépasser 100 caractères',
            'ville.in' => 'La ville sélectionnée doit faire partie de la liste officielle des communes du Bénin.',
            'pays.max' => 'Le pays ne peut pas dépasser 100 caractères',
        ];
    }
}
