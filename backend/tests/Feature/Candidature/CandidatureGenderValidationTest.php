<?php

declare(strict_types=1);

namespace Tests\Feature\Candidature;

use App\Enums\FaceGender;
use App\Models\Face;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidatureGenderValidationTest extends TestCase
{
    use RefreshDatabase;

    private function createFaceUser(?FaceGender $sexe = null): array
    {
        $faceData = $sexe !== null ? ['sexe' => $sexe->value] : [];
        $face = Face::factory()->create($faceData);
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        return [$user, $face];
    }

    private function createMission(string $factoryState = 'forAll'): Mission
    {
        return Mission::factory()->published()->{$factoryState}()->create([
            'date_limite_candidature' => now()->addDays(7),
        ]);
    }

    public function test_homme_face_can_apply_to_homme_mission(): void
    {
        [$user] = $this->createFaceUser(FaceGender::HOMME);
        $mission = $this->createMission('forMen');

        $response = $this->actingAs($user)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/apply");

        $response->assertStatus(201);
    }

    public function test_homme_face_cannot_apply_to_femme_mission(): void
    {
        [$user] = $this->createFaceUser(FaceGender::HOMME);
        $mission = $this->createMission('forWomen');

        $response = $this->actingAs($user)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/apply");

        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'code' => 'gender_mismatch',
                ],
            ]);
    }

    public function test_femme_face_can_apply_to_femme_mission(): void
    {
        [$user] = $this->createFaceUser(FaceGender::FEMME);
        $mission = $this->createMission('forWomen');

        $response = $this->actingAs($user)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/apply");

        $response->assertStatus(201);
    }

    public function test_femme_face_cannot_apply_to_homme_mission(): void
    {
        [$user] = $this->createFaceUser(FaceGender::FEMME);
        $mission = $this->createMission('forMen');

        $response = $this->actingAs($user)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/apply");

        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'code' => 'gender_mismatch',
                ],
            ]);
    }

    public function test_any_face_can_apply_to_tous_mission(): void
    {
        $mission = $this->createMission('forAll');

        foreach (FaceGender::cases() as $gender) {
            [$user] = $this->createFaceUser($gender);

            $response = $this->actingAs($user)
                ->postJson("/api/v1/face/missions/{$mission->uuid}/apply");

            $response->assertStatus(201);
        }
    }

    public function test_autre_face_cannot_apply_to_homme_mission(): void
    {
        [$user] = $this->createFaceUser(FaceGender::AUTRE);
        $mission = $this->createMission('forMen');

        $response = $this->actingAs($user)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/apply");

        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'code' => 'gender_mismatch',
                ],
            ]);
    }

    public function test_autre_face_cannot_apply_to_femme_mission(): void
    {
        [$user] = $this->createFaceUser(FaceGender::AUTRE);
        $mission = $this->createMission('forWomen');

        $response = $this->actingAs($user)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/apply");

        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'code' => 'gender_mismatch',
                ],
            ]);
    }

    public function test_autre_face_can_apply_to_tous_mission(): void
    {
        [$user] = $this->createFaceUser(FaceGender::AUTRE);
        $mission = $this->createMission('forAll');

        $response = $this->actingAs($user)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/apply");

        $response->assertStatus(201);
    }

    public function test_null_sexe_face_cannot_apply_to_gendered_mission(): void
    {
        [$user] = $this->createFaceUser(null);
        $mission = $this->createMission('forMen');

        $response = $this->actingAs($user)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/apply");

        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'code' => 'gender_mismatch',
                    'message' => 'Veuillez compléter votre profil (genre) avant de postuler.',
                ],
            ]);
    }

    public function test_null_sexe_face_can_apply_to_tous_mission(): void
    {
        [$user] = $this->createFaceUser(null);
        $mission = $this->createMission('forAll');

        $response = $this->actingAs($user)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/apply");

        $response->assertStatus(201);
    }

    public function test_gender_mismatch_error_contains_mission_genre_label(): void
    {
        [$user] = $this->createFaceUser(FaceGender::FEMME);
        $mission = $this->createMission('forMen');

        $response = $this->actingAs($user)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/apply");

        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'code' => 'gender_mismatch',
                    'message' => 'Cette mission recherche un profil Homme. Votre profil ne correspond pas au genre requis.',
                ],
            ]);
    }
}
