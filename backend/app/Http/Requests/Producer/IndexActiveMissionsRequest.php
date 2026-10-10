<?php

declare(strict_types=1);

namespace App\Http\Requests\Producer;

use App\Models\Producer;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for the Producer dashboard « Missions actives » module.
 */
class IndexActiveMissionsRequest extends FormRequest
{
    public const DEFAULT_LIMIT = 5;

    public const MAX_LIMIT = 20;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->userable_type === Producer::class;
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ];
    }

    public function limit(): int
    {
        return (int) $this->validated('limit', self::DEFAULT_LIMIT);
    }
}
