<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveAttendanceDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('notes') && is_string($this->input('notes'))) {
            $this->merge([
                'notes' => trim($this->input('notes')),
            ]);
        }
    }

    /**
     * @return array<string, array<int, string|\Illuminate\Validation\Rules\In>>
     */
    public function rules(): array
    {
        return [
            'outcome' => ['required', 'string', Rule::in(['face', 'producer'])],
            'notes' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
