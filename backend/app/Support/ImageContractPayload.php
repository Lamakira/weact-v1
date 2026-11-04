<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ImageContractSupport;
use App\Enums\ImageContractTerritoire;
use App\Models\ImageContract;

/**
 * Représentation API d'un contrat d'autorisation d'image (booking ou candidature).
 * Jamais d'e-mail complet ni de user agent : seulement les noms, dates et conditions.
 */
final class ImageContractPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function for(ImageContract $contract): array
    {
        return [
            'id' => $contract->uuid,
            'status' => $contract->status(),
            'finalite' => $contract->finalite,
            'supports' => array_map(
                fn (string $value): array => ['value' => $value, 'label' => ImageContractSupport::from($value)->label()],
                $contract->supports,
            ),
            'territoire' => [
                'value' => $contract->territoire,
                'label' => ImageContractTerritoire::from($contract->territoire)->label(),
            ],
            'duree_annees' => $contract->duree_annees,
            'producer_signed_at' => $contract->producer_signed_at->toIso8601String(),
            'producer_signed_name' => $contract->producer_signed_name,
            'face_signed_at' => $contract->face_signed_at?->toIso8601String(),
            'face_signed_name' => $contract->face_signed_name,
            'guardian_name' => $contract->guardian_name,
            'effective_at' => $contract->effective_at?->toIso8601String(),
            'pdf_ready' => $contract->pdf_path !== null,
        ];
    }
}
