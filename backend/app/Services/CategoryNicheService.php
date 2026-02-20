<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Face;

class CategoryNicheService
{
    /**
     * Update category and niche fields for a Face.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateCategoryNiche(Face $face, array $data): Face
    {
        $updateData = [];

        if (array_key_exists('categories', $data)) {
            $updateData['categories'] = $data['categories'] ?? [];
        }

        if (array_key_exists('niches', $data)) {
            $updateData['niches'] = $data['niches'] ?? [];
        }

        if (! empty($updateData)) {
            $face->update($updateData);
        }

        return $face->refresh();
    }
}
