<?php

declare(strict_types=1);

namespace App\Http\Requests\Face;

use App\Enums\FaceVideoType;
use App\Exceptions\VideoQuotaReachedException;
use App\Models\Face;
use App\Services\FaceEntitlementService;
use App\Services\FaceVideoService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UploadFaceVideoRequest extends FormRequest
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
            'type' => ['required', Rule::enum(FaceVideoType::class)],
            'video' => [
                'required',
                File::types(['mp4', 'mov', 'avi'])->max(50 * 1024), // 50MB in KB
                'mimetypes:video/mp4,video/quicktime,video/x-msvideo',
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            if ($user === null || $user->userable_type !== Face::class) {
                return;
            }

            /** @var Face $face */
            $face = $user->userable;
            $type = FaceVideoType::from((string) $this->input('type'));

            // Per-type tier quota first (one cheap count query) — skip the
            // FFProbe duration probe when the upload is blocked anyway.
            $capabilities = app(FaceEntitlementService::class)->capabilities($face);
            $limit = $type === FaceVideoType::Acting
                ? $capabilities->maxActingVideos
                : $capabilities->maxUgcVideos;
            $currentCount = $face->videos()->where('type', $type)->count();

            if ($currentCount >= $limit) {
                // Reuse VideoQuotaReachedException as the single source of the
                // tier-aware quota message so the FormRequest and service paths
                // cannot drift apart.
                $validator->errors()->add(
                    'video',
                    (new VideoQuotaReachedException($limit, $type))->getMessage()
                );

                return;
            }

            $video = $this->file('video');
            if ($video !== null) {
                try {
                    $duration = app(FaceVideoService::class)->getVideoDuration($video);
                } catch (\Throwable $e) {
                    // An unreadable/corrupt-but-valid-looking video must surface
                    // as a 422 validation error, not an uncaught 500.
                    $validator->errors()->add('video', $this->messages()['video.unreadable']);

                    return;
                }

                if ($duration > FaceVideoService::getMaxDurationSeconds()) {
                    $validator->errors()->add('video', $this->messages()['video.duration']);
                }
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
            'type.required' => 'Le type de vidéo est requis.',
            'type.enum' => 'Type de vidéo invalide.',
            'video.required' => 'La vidéo est requise.',
            'video.mimetypes' => 'Format non supporté (MP4, MOV, AVI uniquement).',
            'video.max' => 'Vidéo trop volumineuse (max 50MB).',
            'video.duration' => 'Vidéo trop longue (max 2 minutes).',
            'video.unreadable' => 'Vidéo illisible ou corrompue.',
        ];
    }
}
