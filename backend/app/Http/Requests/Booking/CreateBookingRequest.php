<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
                'string',
                'uuid',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $face = Face::where('uuid', $value)->first();
                    if (! $face) {
                        $fail('La Face sélectionnée n\'existe pas.');

                        return;
                    }
                    $user = User::where('userable_type', Face::class)
                        ->where('userable_id', $face->id)
                        ->first();
                    if (! $user) {
                        $fail('L\'utilisateur associé à cette Face est introuvable.');
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $faceUuid = $this->input('face_id');
            $dateDebut = $this->input('date_debut');
            $dateFin = $this->input('date_fin');
            $dureeHeures = $this->integer('duree_heures');

            if (! is_string($faceUuid) || ! is_string($dateDebut) || ! is_string($dateFin) || $dureeHeures <= 0) {
                return;
            }

            $face = Face::where('uuid', $faceUuid)->first();
            $faceUser = $face ? User::where('userable_type', Face::class)->where('userable_id', $face->id)->first() : null;
            if (! $faceUser) {
                return;
            }
            $faceId = $faceUser->id;

            try {
                $start = CarbonImmutable::parse($dateDebut)->startOfDay();
                $end = CarbonImmutable::parse($dateFin)->endOfDay();
            } catch (\Throwable) {
                return;
            }

            $inclusiveDays = $start->startOfDay()->diffInDays($end->startOfDay()) + 1;
            $maxHoursForRange = $inclusiveDays * 8;

            if ($dureeHeures > $maxHoursForRange) {
                $validator->errors()->add(
                    'duree_heures',
                    "La duree selectionnee depasse le maximum de {$maxHoursForRange} heures pour cette plage de dates."
                );
            }

            $overlapExists = Booking::query()
                ->where('face_id', $faceId)
                ->whereIn('status', [
                    BookingStatus::Accepted->value,
                    BookingStatus::Paid->value,
                    BookingStatus::ConfirmedByFace->value,
                    BookingStatus::ConfirmedByProducer->value,
                ])
                ->where(function ($query) use ($start, $end): void {
                    $query->where('date_debut', '<=', $end)
                        ->where('date_fin', '>=', $start);
                })
                ->exists();

            if ($overlapExists) {
                $validator->errors()->add(
                    'date_debut',
                    'Cette Face a deja un booking actif sur cette plage de dates.'
                );
            }
        });
    }
}
