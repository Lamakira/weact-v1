<?php

declare(strict_types=1);

namespace App\Http\Requests\Ugc;

use App\Models\Face;
use App\Models\Shipment;
use App\Services\Ugc\UgcDeliverableService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\File;

/**
 * Validation du fichier livrable (calque UploadFaceVideoRequest). authorize()
 * exige « est une Face » ET la propriété du deal (Gate uploadDeliverable sur le
 * {shipment} de la route) : un non-propriétaire reçoit 403 AVANT toute
 * validation/sonde ffprobe du fichier (jusqu'à 200 Mo). Limites lues depuis
 * config('ugc.media').
 */
class UploadDeliverableRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null
            || $user->userable_type !== Face::class
            || $user->userable_id === null) {
            return false;
        }

        $shipment = $this->route('shipment');

        return $shipment instanceof Shipment
            && Gate::forUser($user)->allows('uploadDeliverable', $shipment);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var array{max_size_mb: int, allowed_extensions: array<int, string>, allowed_mimetypes: array<int, string>, max_duration_seconds: int|null} $media */
        $media = config('ugc.media');

        return [
            'video' => [
                'required',
                File::types($media['allowed_extensions'])->max($media['max_size_mb'] * 1024),
                'mimetypes:'.implode(',', $media['allowed_mimetypes']),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return; // déjà rejeté (taille/format) — ne pas sonder un fichier invalide
            }

            $video = $this->file('video');
            if (! $video instanceof UploadedFile) {
                return;
            }

            try {
                $duration = app(UgcDeliverableService::class)->getVideoDuration($video);
            } catch (\Throwable) {
                $validator->errors()->add('video', $this->messages()['video.unreadable']);

                return;
            }

            $cap = config('ugc.media.max_duration_seconds'); // null = pas de plafond pour un livrable
            if ($cap !== null && $duration > (float) $cap) {
                $validator->errors()->add('video', "Vidéo trop longue (max {$cap}s).");
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxMb = config('ugc.media.max_size_mb');

        return [
            'video.required' => 'La vidéo est requise.',
            'video.mimetypes' => 'Format non supporté (MP4, MOV, AVI uniquement).',
            'video.max' => "Vidéo trop volumineuse (max {$maxMb}MB).",
            'video.unreadable' => 'Vidéo illisible ou corrompue.',
        ];
    }
}
