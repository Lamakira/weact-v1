<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class CreateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->userable_type === Producer::class;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'face_id' => [
                'required',
                'integer',
                'exists:users,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $user = User::find($value);
                    if (! $user || $user->userable_type !== Face::class) {
                        $fail('L\'utilisateur selectionne n\'est pas une Face.');
                    }
                },
            ],
            'date_debut' => ['required', 'date', 'after:today'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'duree_heures' => ['required', 'integer', 'min:4', 'max:720'],
            'type_contenu' => ['required', 'string', 'max:100'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'face_id.required' => 'La Face est obligatoire.',
            'face_id.exists' => 'La Face selectionnee n\'existe pas.',
            'date_debut.required' => 'La date de debut est obligatoire.',
            'date_debut.after' => 'La date de debut doit etre dans le futur.',
            'date_fin.required' => 'La date de fin est obligatoire.',
            'date_fin.after_or_equal' => 'La date de fin doit etre le jour meme ou apres la date de debut.',
            'duree_heures.required' => 'La duree est obligatoire.',
            'duree_heures.min' => 'La duree minimale est de 4 heures.',
            'duree_heures.max' => 'La duree ne peut pas depasser :max heures.',
            'type_contenu.required' => 'Le type de contenu est obligatoire.',
            'type_contenu.max' => 'Le type de contenu ne peut pas depasser :max caracteres.',
            'message.max' => 'Le message ne peut pas depasser :max caracteres.',
        ];
    }
}
