<?php

declare(strict_types=1);

namespace App\Http\Requests\ImageContract;

use App\Services\ImageContract\SignatureChallengeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestSignatureCodeRequest extends FormRequest
{
    public const PURPOSE_BOOKING_ACCEPT = 'booking_accept';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'purpose' => ['required', 'string', Rule::in([SignatureChallengeService::PURPOSE_BOOKING_REQUEST, self::PURPOSE_BOOKING_ACCEPT])],
            'booking_id' => ['required_if:purpose,'.self::PURPOSE_BOOKING_ACCEPT, 'nullable', 'string', 'uuid'],
            // Requis pour une Face de moins de 18 ans (l'âge est lu côté serveur, pas déclaré).
            'guardian_name' => ['nullable', 'string', 'min:3', 'max:150'],
            'guardian_relation' => ['nullable', 'string', Rule::in(['mere', 'pere', 'tuteur'])],
            'guardian_email' => ['nullable', 'string', 'email:rfc', 'max:254'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'purpose.required' => 'La finalité du code est obligatoire.',
            'purpose.in' => 'La finalité du code est invalide.',
            'booking_id.required_if' => 'Le booking est obligatoire.',
            'guardian_name.min' => 'Le nom du parent ou tuteur doit comporter au moins :min caractères.',
            'guardian_relation.in' => 'Le lien avec le parent ou tuteur est invalide.',
            'guardian_email.email' => 'L\'adresse e-mail du parent ou tuteur est invalide.',
        ];
    }
}
