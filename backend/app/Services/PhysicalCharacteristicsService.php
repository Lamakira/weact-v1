<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Face;

class PhysicalCharacteristicsService
{
    /**
     * Update physical characteristics fields for a Face.
     *
     * @param  array<string, mixed>  $data
     */
    public function updatePhysicalCharacteristics(Face $face, array $data): Face
    {
        $updateData = [];

        if (array_key_exists('taille', $data)) {
            $updateData['taille'] = $data['taille'];
        }

        if (array_key_exists('poids', $data)) {
            $updateData['poids'] = $data['poids'];
        }

        if (! empty($updateData)) {
            $face->update($updateData);
        }

        return $face->refresh();
    }
}
