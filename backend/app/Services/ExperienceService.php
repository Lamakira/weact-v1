<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Experience;
use App\Models\Face;
use Illuminate\Database\Eloquent\Collection;

class ExperienceService
{
    /**
     * Get all experiences for a Face, ordered by date descending (most recent first).
     *
     * @return Collection<int, Experience>
     */
    public function getExperiences(Face $face): Collection
    {
        /** @var Collection<int, Experience> $experiences */
        $experiences = $face->experiences()
            ->orderByDesc('date_debut')
            ->get();

        return $experiences;
    }

    /**
     * Create a new experience for a Face.
     *
     * @param  array<string, mixed>  $data
     */
    public function createExperience(Face $face, array $data): Experience
    {
        /** @var Experience $experience */
        $experience = $face->experiences()->create([
            'titre' => $data['titre'],
            'description' => $data['description'] ?? null,
            'date_debut' => $data['date_debut'],
            'date_fin' => $data['date_fin'] ?? null,
        ]);

        return $experience;
    }

    /**
     * Update an existing experience.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateExperience(Experience $experience, array $data): Experience
    {
        $experience->update([
            'titre' => $data['titre'],
            'description' => $data['description'] ?? null,
            'date_debut' => $data['date_debut'],
            'date_fin' => $data['date_fin'] ?? null,
        ]);

        return $experience->refresh();
    }

    /**
     * Delete an experience.
     */
    public function deleteExperience(Experience $experience): bool
    {
        return $experience->delete();
    }
}
