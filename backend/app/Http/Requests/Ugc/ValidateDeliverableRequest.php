<?php

declare(strict_types=1);

namespace App\Http\Requests\Ugc;

use App\Models\Producer;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtre « est un Producteur » pour valider un livrable UGC (4.3). La propriété
 * du deal est vérifiée par Gate::authorize('review', $deliverable) dans le
 * contrôleur (garde owner = DeliverablePolicy::review). Aucun champ de payload :
 * la validation est une action sans corps.
 */
class ValidateDeliverableRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->userable_type === Producer::class
            && $user->userable_id !== null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
