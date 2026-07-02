<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CancelFaceSubscriptionRequest extends FormRequest
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
        ];
    }
}
