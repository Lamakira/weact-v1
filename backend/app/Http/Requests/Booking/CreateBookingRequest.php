<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use App\Enums\BookingStatus;
use App\Enums\CompensationType;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use App\ValueObjects\BookingPricing;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class CreateBookingRequest extends FormRequest
{
    private const MAX_UNSIGNED_INTEGER = 4294967295;

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
        $rules = [
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
            'lieu' => ['required', 'string', 'max:100'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];

        // UGC : compensation produit seul ou hybride.
        if ($this->input('type_contenu') === 'UGC') {
            // Une dotation UGC n'a ni lieu, ni dates, ni durée de tournage (la Face filme
            // chez elle, à son rythme) → on relâche ces champs cash en nullable pour l'UGC.
            $rules['date_debut'] = ['nullable', 'date', 'after:today'];
            $rules['date_fin'] = ['nullable', 'date', 'after_or_equal:date_debut'];
            $rules['duree_heures'] = ['nullable', 'integer', 'min:4', 'max:720'];
            $rules['lieu'] = ['nullable', 'string', 'max:100'];

            $rules['type_compensation'] = ['required', Rule::in(CompensationType::values())];
            $rules['nom_produit'] = ['required', 'string', 'max:255'];
            $rules['valeur_produit'] = ['required', 'integer', 'min:1', 'max:'.self::MAX_UNSIGNED_INTEGER];

            // Photos produit (spec photos produit) : 0 à 2, optionnelles, calque album
            // (File::image jpg/png 8 Mo). Règles posées ICI (jamais dans le trait
            // partagé MissionValidationRules — leçon ugc-1-3).
            $rules['product_photos'] = ['nullable', 'array', 'max:2'];
            $rules['product_photos.*'] = [
                File::image()
                    ->types(['jpg', 'jpeg', 'png'])
                    ->max(8 * 1024), // 8 Mo en Ko
            ];

            if ($this->input('type_compensation') === CompensationType::Hybrid->value) {
                $rules['nombre_videos'] = ['required', 'integer', 'min:2', 'max:20']; // était min:1 (ugc-4-0, option B)
                $rules['montant_remuneration'] = ['required', 'integer', 'min:1', 'max:'.self::MAX_UNSIGNED_INTEGER];
            }
            // Produit seul : nombre_videos forcé serveur à 2 (BookingService), montant_remuneration ignoré.
        }

        return $rules;
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
            'lieu.required' => 'Le lieu de tournage est obligatoire.',
            'lieu.max' => 'Le lieu ne peut pas depasser :max caracteres.',
            'message.max' => 'Le message ne peut pas depasser :max caracteres.',
            'type_compensation.required' => 'Le type de compensation est obligatoire.',
            'type_compensation.in' => 'Le type de compensation est invalide.',
            'nom_produit.required' => 'Le nom du produit est obligatoire.',
            'nom_produit.max' => 'Le nom du produit ne peut pas dépasser :max caractères.',
            'valeur_produit.required' => 'La valeur du produit est obligatoire.',
            'valeur_produit.integer' => 'La valeur du produit doit être un nombre entier.',
            'valeur_produit.min' => 'La valeur du produit doit être supérieure ou égale à :min.',
            'valeur_produit.max' => 'La valeur du produit est trop élevée.',
            'nombre_videos.required' => 'Le nombre de vidéos est obligatoire.',
            'nombre_videos.integer' => 'Le nombre de vidéos doit être un nombre entier.',
            'nombre_videos.min' => 'Le nombre de vidéos doit être au moins de :min.',
            'nombre_videos.max' => 'Le nombre de vidéos ne peut pas dépasser :max.',
            'montant_remuneration.required' => 'Le montant de la rémunération est obligatoire.',
            'montant_remuneration.integer' => 'Le montant de la rémunération doit être un nombre entier.',
            'montant_remuneration.min' => 'Le montant de la rémunération doit être supérieur ou égal à :min.',
            'montant_remuneration.max' => 'Le montant de la rémunération est trop élevé.',
            'product_photos.array' => 'Les photos du produit doivent être une liste de fichiers.',
            'product_photos.max' => 'Vous ne pouvez joindre que :max photos du produit.',
            'product_photos.*.image' => 'Chaque photo du produit doit être une image.',
            'product_photos.*.mimes' => 'Chaque photo du produit doit être au format JPG ou PNG.',
            'product_photos.*.max' => 'Chaque photo du produit ne doit pas dépasser 8 Mo.',
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

            $this->validateUgcTotalFitsStorage($validator);
        });
    }

    private function validateUgcTotalFitsStorage(Validator $validator): void
    {
        if ($this->input('type_contenu') !== 'UGC') {
            return;
        }

        if (
            $validator->errors()->has('type_compensation')
            || $validator->errors()->has('valeur_produit')
            || $validator->errors()->has('montant_remuneration')
        ) {
            return;
        }

        $valeurProduit = $this->integer('valeur_produit');
        if ($valeurProduit <= 0) {
            return;
        }

        $isHybrid = $this->input('type_compensation') === CompensationType::Hybrid->value;
        $montantRemuneration = $isHybrid ? $this->integer('montant_remuneration') : 0;

        // RH.2 : doit refléter le montant_total_producteur RÉELLEMENT persisté par
        // BookingService::createUgcBooking, sinon la colonne unsignedInteger peut déborder :
        //  - produit seul → commission sur la valeur produit (plancher inclus) = montant_total_producteur ;
        //  - hybride      → total = cash + frais service Producteur 10 % flat (BookingPricing). Le palier
        //    Face n'entre pas dans totalProducerPays (il ne réduit que le net Face) → faceCommissionRate=0.0
        //    donne le total EXACT sans coupler ce FormRequest à FaceEntitlementService.
        if ($isHybrid) {
            $total = (new BookingPricing($montantRemuneration, 0.0))->totalProducerPays;
        } else {
            $rate = (float) config('ugc.commission_rate');
            $floor = (int) config('ugc.commission_floor');
            $total = max($floor, (int) round($valeurProduit * $rate));
        }

        if ($total > self::MAX_UNSIGNED_INTEGER) {
            $validator->errors()->add(
                'montant_remuneration',
                'Le montant total du booking UGC est trop élevé.'
            );
        }
    }
}
