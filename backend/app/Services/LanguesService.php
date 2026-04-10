<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Face;

class LanguesService
{
    /**
     * Update langues for a Face.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateLangues(Face $face, array $data): Face
    {
        $face->update([
            'langues' => ! empty($data['langues']) ? $data['langues'] : null,
        ]);

        return $face->refresh();
    }
}
