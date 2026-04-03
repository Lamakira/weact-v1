<?php

declare(strict_types=1);

namespace App\Http\Requests\Face;

use App\Enums\FaceCategory;
use App\Enums\FaceNiche;
use App\Models\Face;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateCategoryNicheRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->userable_type === Face::class;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'categories' => ['nullable', 'array'],
            'categories.*' => [new Enum(FaceCategory::class), Rule::notIn([FaceCategory::INFLUENCEUR->value])],
            'niches' => ['nullable', 'array'],
            'niches.*' => [new Enum(FaceNiche::class)],
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
            'categories.array' => 'Les catégories doivent être un tableau',
            'categories.*.Illuminate\Validation\Rules\Enum' => "La catégorie sélectionnée n'est pas valide",
            'niches.array' => 'Les niches doivent être un tableau',
            'niches.*.Illuminate\Validation\Rules\Enum' => "La niche sélectionnée n'est pas valide",
        ];
    }
}
