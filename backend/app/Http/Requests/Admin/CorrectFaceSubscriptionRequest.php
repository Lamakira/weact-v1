<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class CorrectFaceSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('notes') && is_string($this->input('notes'))) {
            $this->merge(['notes' => trim($this->input('notes'))]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'notes' => ['required', 'string', 'min:5', 'max:1000'],
            'starts_at' => ['sometimes', 'date', 'after_or_equal:2020-01-01', 'before_or_equal:+10 years'],
            'expires_at' => ['sometimes', 'date', 'after_or_equal:2020-01-01', 'before_or_equal:+10 years', 'after:starts_at'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $hasStarts = $this->has('starts_at');
            $hasExpires = $this->has('expires_at');

            if (! $hasStarts && ! $hasExpires) {
                $v->errors()->add(
                    'expires_at',
                    'Au moins une des dates starts_at ou expires_at est requise.',
                );

                return;
            }

            if ($v->errors()->has('starts_at') || $v->errors()->has('expires_at')) {
                return;
            }

            /** @var \App\Models\FaceSubscription|null $subscription */
            $subscription = $this->route('subscription');

            if ($subscription === null) {
                return;
            }

            $newStarts = $hasStarts
                ? Carbon::parse($this->input('starts_at'))
                : $subscription->starts_at;
            $newExpires = $hasExpires
                ? Carbon::parse($this->input('expires_at'))
                : $subscription->expires_at;

            if ($hasStarts && ! $hasExpires && ! ($newExpires instanceof CarbonInterface)) {
                $v->errors()->add(
                    'expires_at',
                    'expires_at est requis lorsque l\'abonnement n\'a pas encore de date de fin.',
                );

                return;
            }

            if (! $hasStarts && $hasExpires && ! ($newStarts instanceof CarbonInterface)) {
                $v->errors()->add(
                    'starts_at',
                    'starts_at est requis lorsque l\'abonnement n\'a pas encore de date de début.',
                );

                return;
            }

            if ($newStarts instanceof CarbonInterface
                && $newExpires instanceof CarbonInterface
                && $newExpires->lessThanOrEqualTo($newStarts)
            ) {
                $v->errors()->add(
                    'expires_at',
                    'expires_at doit être postérieure à starts_at.',
                );
            }
        });
    }
}
