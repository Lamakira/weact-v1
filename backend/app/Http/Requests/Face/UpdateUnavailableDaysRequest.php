<?php

declare(strict_types=1);

namespace App\Http\Requests\Face;

use App\Models\Face;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUnavailableDaysRequest extends FormRequest
{
    public const MAX_DATES = 62;

    public function authorize(): bool
    {
        return $this->user()?->userable_type === Face::class;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'add' => ['sometimes', 'nullable', 'array', 'max:'.self::MAX_DATES],
            'add.*' => ['required', 'string', 'date_format:Y-m-d', 'distinct'],
            'remove' => ['sometimes', 'nullable', 'array', 'max:'.self::MAX_DATES],
            'remove.*' => ['required', 'string', 'date_format:Y-m-d', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'add.array' => 'La liste des jours à bloquer est invalide.',
            'add.max' => 'Vous ne pouvez pas bloquer plus de :max jours à la fois.',
            'add.*.date_format' => 'Chaque jour à bloquer doit être au format AAAA-MM-JJ.',
            'add.*.distinct' => 'Un même jour ne peut pas apparaître deux fois.',
            'remove.array' => 'La liste des jours à libérer est invalide.',
            'remove.max' => 'Vous ne pouvez pas libérer plus de :max jours à la fois.',
            'remove.*.date_format' => 'Chaque jour à libérer doit être au format AAAA-MM-JJ.',
            'remove.*.distinct' => 'Un même jour ne peut pas apparaître deux fois.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->days('add') === [] && $this->days('remove') === []) {
                $validator->errors()->add('add', 'Indiquez au moins un jour à bloquer ou à libérer.');
            }
        });
    }

    /**
     * @return list<string>
     */
    public function days(string $key): array
    {
        $value = $this->input($key);

        return is_array($value) ? array_values(array_map('strval', $value)) : [];
    }
}
