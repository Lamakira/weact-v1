<?php

declare(strict_types=1);

namespace App\Http\Requests\Agenda;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Plage `from`/`to` d'un calendrier : dates `Y-m-d`, `to >= from`, 92 jours d'écart au plus.
 * Les sous-classes décident de l'accès.
 */
abstract class AgendaRangeRequest extends FormRequest
{
    public const MAX_SPAN_DAYS = 92;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'string', 'date_format:Y-m-d'],
            'to' => ['required', 'string', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from.required' => 'La date de début est requise.',
            'from.date_format' => 'La date de début doit être au format AAAA-MM-JJ.',
            'to.required' => 'La date de fin est requise.',
            'to.date_format' => 'La date de fin doit être au format AAAA-MM-JJ.',
            'to.after_or_equal' => 'La date de fin ne peut pas précéder la date de début.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['from', 'to'])) {
                return;
            }

            $span = Carbon::parse((string) $this->input('from'))->diffInDays(Carbon::parse((string) $this->input('to')));

            if ($span > self::MAX_SPAN_DAYS) {
                $validator->errors()->add('to', 'La période demandée ne peut pas dépasser '.self::MAX_SPAN_DAYS.' jours.');
            }
        });
    }
}
