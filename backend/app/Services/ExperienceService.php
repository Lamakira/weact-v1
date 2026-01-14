<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Experience;
use App\Models\Face;
use Illuminate\Database\Eloquent\Collection;

class ExperienceService
{
    /**
     * Get all experiences for a Face, ordered by year descending (newest first).
     *
     * @return Collection<int, Experience>
     */
    public function getExperiences(Face $face): Collection
    {
        return $face->experiences()->orderedByYear()->get();
    }

    /**
     * Create a new experience for a Face.
     *
     * @param  array<string, mixed>  $data
     */
    public function createExperience(Face $face, array $data): Experience
    {
        return $face->experiences()->create([
            'titre' => $data['titre'],
            'description' => $data['description'] ?? null,
            'annee' => $data['annee'],
        ]);
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
            'annee' => $data['annee'],
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
