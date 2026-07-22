<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Public;

use App\Constants\BeninCities;
use App\Enums\FaceCategory;
use App\Enums\FaceNiche;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request for public faces list endpoint.
 *
 * Validates pagination and filter parameters for the public faces listing.
 * No authentication required as this is a public endpoint.
 */
class ListFacesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Always true for public endpoints.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1'],
            'categorie' => ['sometimes', 'nullable', Rule::enum(FaceCategory::class)],
            'niche' => ['sometimes', 'nullable', Rule::enum(FaceNiche::class)],
            'ville' => ['sometimes', 'nullable', 'string', Rule::in(BeninCities::values())],
            'search' => ['sometimes', 'nullable', 'string', 'min:2', 'max:255'],
            // Génération de classement épinglée par le visiteur (carrousel).
            // Jamais dans l'URL de la page : le front la garde en état local et
            // ne la met que dans la requête HTTP.
            //
            // `nullable` : une page PUBLIQUE ne rend pas 422 sur un
            // `?generation=` vide (proxy qui recopie les clés, URL construite à
            // la main, client qui sérialise un null). Absente ou vide = pas
            // d'épinglage, exactement le même chemin.
            'generation' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Get validated per_page value with default, capped at 30.
     *
     * 16 = full grid rows on the public listing (4×4 desktop, 8×2 mobile,
     * commit 55895ef9) AND the LRU page-1 exposure window
     * (FaceListingRankingService::PAGE_ONE_WINDOW) — change them together.
     */
    public function getPerPage(): int
    {
        $perPage = (int) ($this->validated()['per_page'] ?? 16);

        return min($perPage, 30);
    }

    /**
     * Get validated page value with default.
     */
    public function getPage(): int
    {
        return (int) $this->validated('page', 1);
    }

    /**
     * Génération de classement demandée, ou null si le visiteur n'en épingle
     * aucune (première page d'un parcours, ou rotation désactivée).
     *
     * Le contrôleur reste seul juge : une génération purgée entre-temps, ou un
     * filtre actif, la font ignorer silencieusement — jamais de 4xx, ce
     * paramètre est une préférence de continuité, pas une ressource.
     */
    public function getGeneration(): ?int
    {
        $generation = $this->validated('generation');

        return $generation === null ? null : (int) $generation;
    }
}
