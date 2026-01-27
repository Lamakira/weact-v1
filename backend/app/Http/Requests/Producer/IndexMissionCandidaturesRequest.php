<?php

declare(strict_types=1);

namespace App\Http\Requests\Producer;

use App\Enums\CandidatureStatus;
use App\Models\Producer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request for listing candidatures of a Producer's mission.
 *
 * Validates optional status filter parameter.
 */
class IndexMissionCandidaturesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled in controller (mission ownership check)
        // Here we just verify the user is a Producer
        $user = $this->user();

        return $user && $user->userable_type === Producer::class;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'nullable',
                'string',
                Rule::in(CandidatureStatus::values()),
            ],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Le statut sélectionné est invalide.',
        ];
    }

    /**
     * Get the validated status as CandidatureStatus enum or null.
     */
    public function getStatusFilter(): ?CandidatureStatus
    {
        $status = $this->validated('status');

        if (! $status) {
            return null;
        }

        return CandidatureStatus::tryFrom($status);
    }
}
