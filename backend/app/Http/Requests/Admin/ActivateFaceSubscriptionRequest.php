<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\FaceSubscriptionPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivateFaceSubscriptionRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan' => ['required', Rule::enum(FaceSubscriptionPlan::class)],
            'notes' => ['required', 'string', 'min:5', 'max:1000'],
            'starts_at' => ['sometimes', 'date', 'after_or_equal:-90 days', 'before_or_equal:now'],
            'duration_days' => ['sometimes', 'integer', 'min:30', 'max:3650'],
        ];
    }
}
