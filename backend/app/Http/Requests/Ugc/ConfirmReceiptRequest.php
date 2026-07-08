<?php

declare(strict_types=1);

namespace App\Http\Requests\Ugc;

use App\Models\Face;
use App\Models\Shipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\File;

/**
 * Validation des photos de réception UGC (calque AddAlbumPhotoRequest +
 * UploadDeliverableRequest). 1 à 2 photos JPG/PNG obligatoires — le cœur de la
 * preuve « produit reçu » (spec réception).
 *
 * authorize() vérifie « est une Face » ET la propriété du deal (policy
 * confirmReceipt) : le 403 d'autorisation doit précéder le 422 de validation,
 * pour qu'une Face non-propriétaire n'apprenne pas le contrat photos via un
 * message de validation. Le contrôleur conserve son Gate::authorize en
 * défense-en-profondeur.
 */
class ConfirmReceiptRequest extends FormRequest
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
            && Gate::forUser($user)->allows('confirmReceipt', $shipment);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reception_photos' => ['required', 'array', 'min:1', 'max:2'],
            'reception_photos.*' => [
                File::image()
                    ->types(['jpg', 'jpeg', 'png'])
                    ->max(8 * 1024), // 8 Mo en Ko
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reception_photos.required' => 'Au moins une photo du produit reçu est requise.',
            'reception_photos.array' => 'Les photos de réception doivent être une liste de fichiers.',
            'reception_photos.min' => 'Au moins une photo du produit reçu est requise.',
            'reception_photos.max' => 'Vous ne pouvez joindre que :max photos de réception.',
            'reception_photos.*.image' => 'Chaque photo de réception doit être une image.',
            'reception_photos.*.mimes' => 'Chaque photo de réception doit être au format JPG ou PNG.',
            'reception_photos.*.max' => 'Chaque photo de réception ne doit pas dépasser 8 Mo.',
        ];
    }
}
