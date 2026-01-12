<?php

declare(strict_types=1);

namespace App\Http\Requests\Face;

use App\Models\Face;
use App\Services\ActingVideoService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UploadActingVideoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->userable_type === Face::class
            && $user->userable_id !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'video' => [
                'required',
                File::types(['mp4', 'mov', 'avi'])
                    ->max(50 * 1024), // 50MB in KB
                'mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/avi',
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $video = $this->file('video');
            if (! $video) {
                return;
            }

            $service = app(ActingVideoService::class);
            $duration = $service->getVideoDuration($video);

            if ($duration > ActingVideoService::getMaxDurationSeconds()) {
                $validator->errors()->add('video', $this->messages()['video.duration']);
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'video.required' => 'La vidéo est requise',
            'video.mimetypes' => 'Format non supporté (MP4, MOV, AVI uniquement)',
            'video.max' => 'Vidéo trop volumineuse (max 50MB)',
            'video.duration' => 'Vidéo trop longue (max 2 minutes)',
        ];
    }
}
