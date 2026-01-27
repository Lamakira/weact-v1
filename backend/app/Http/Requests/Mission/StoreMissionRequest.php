<?php

declare(strict_types=1);

namespace App\Http\Requests\Mission;

use App\Models\Producer;
use Illuminate\Foundation\Http\FormRequest;

class StoreMissionRequest extends FormRequest
{
    use MissionValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     * Only Producers can create missions.
     */
    public function authorize(): bool
    {
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
        return $this->missionRules();
    }

    /**
     * Get custom messages for validator errors in French.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->missionMessages();
    }
}
