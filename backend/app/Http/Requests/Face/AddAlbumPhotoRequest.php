<?php

declare(strict_types=1);

namespace App\Http\Requests\Face;

use App\Models\Face;
use App\Services\FaceEntitlementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class AddAlbumPhotoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // User must be authenticated and be a Face
        $user = $this->user();

        return $user !== null && $user->userable_type === Face::class;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                File::image()
                    ->types(['jpg', 'jpeg', 'png'])
                    ->max(8 * 1024), // 8MB in KB
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => 'Une photo est requise.',
            'photo.image' => 'Le fichier doit être une image.',
            'photo.mimes' => 'Le fichier doit être au format JPG ou PNG.',
            'photo.max' => 'Le fichier ne doit pas dépasser 8 Mo.',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            if ($user && $user->userable_type === Face::class) {
                /** @var Face $face */
                $face = $user->userable;
                $photoCount = $face->photos()->count();
                $limit = app(FaceEntitlementService::class)->albumUploadLimit($face);

                if ($photoCount >= $limit) {
                    $validator->errors()->add(
                        'photo',
                        'Quota de '.$limit.' photos atteint'
                    );
                }
            }
        });
    }
}
