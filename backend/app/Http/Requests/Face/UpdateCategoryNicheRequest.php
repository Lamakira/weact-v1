<?php

declare(strict_types=1);

namespace App\Http\Requests\Face;

use App\Enums\FaceCategory;
use App\Enums\FaceNiche;
use App\Models\Face;
use Illuminate\Foundation\Http\FormRequest;
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
            'categorie' => ['nullable', new Enum(FaceCategory::class)],
            'niche' => ['nullable', new Enum(FaceNiche::class)],
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
            'categorie.Illuminate\Validation\Rules\Enum' => "La catégorie sélectionnée n'est pas valide",
            'niche.Illuminate\Validation\Rules\Enum' => "La niche sélectionnée n'est pas valide",
        ];
    }
}
