<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Face;

class BioLocationService
{
    /**
     * Update bio and location fields for a Face.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateBioLocation(Face $face, array $data): Face
    {
        $updateData = [];

        if (array_key_exists('bio', $data)) {
            $updateData['bio'] = $data['bio'];
        }

        if (array_key_exists('ville', $data)) {
            $updateData['ville'] = $data['ville'];
        }

        if (array_key_exists('quartier', $data)) {
            $updateData['quartier'] = $data['quartier'];
        }

        if (array_key_exists('pays', $data)) {
            $updateData['pays'] = $data['pays'];
        }

        if (! empty($updateData)) {
            $face->update($updateData);
        }

        return $face->refresh();
    }
}
