<?php

declare(strict_types=1);

namespace App\Http\Requests\Mission;

use App\Models\Mission;
use Illuminate\Foundation\Http\FormRequest;

class PayUgcMissionCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $mission = $this->route('mission');

        return $mission instanceof Mission
            && (bool) $this->user()?->can('payCommission', $mission);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
