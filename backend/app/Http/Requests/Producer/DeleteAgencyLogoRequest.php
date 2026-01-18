<?php

declare(strict_types=1);

namespace App\Http\Requests\Producer;

use App\Models\Producer;
use Illuminate\Foundation\Http\FormRequest;

class DeleteAgencyLogoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (!$user || !$user->userable instanceof Producer) {
            return false;
        }

        // CRITICAL: Only Agency producers can delete logos
        return $user->userable->isAgency();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
