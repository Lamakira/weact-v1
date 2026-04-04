<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\MissionStatus;
use App\Enums\ProducerType;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProducerProfileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a Producer with an active User (required by public visibility filter).
     */
    private function createProducerWithUser(array $attributes = []): Producer
    {
        $producer = Producer::factory()->create($attributes);
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
            'is_active' => true,
        ]);

        return $producer;
    }

    public function test_unauthenticated_user_can_view_producer_profile(): void
    {
        $producer = $this->createProducerWithUser([
            'type' => ProducerType::Particulier,
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'bio' => 'Test bio content',
        ]);

        $response = $this->getJson("/api/v1/public/producers/{$producer->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'type',
                    'display_name',
                    'agency_name',
                    'bio',
                    'profile_photo_url',
                    'thumbnail_url',
                    'agency_logo_url',
                    'agency_logo_thumbnail_url',
                    'missions_count',
                    'average_rating',
                    'ratings_count',
                    'member_since',
                ],
            ])
            ->assertJsonPath('data.id', $producer->id)
            ->assertJsonPath('data.type', 'particulier')
            ->assertJsonPath('data.display_name', 'Jean Dupont')
            ->assertJsonPath('data.bio', 'Test bio content');
    }

    public function test_authenticated_face_can_view_producer_profile(): void
    {
        $producer = $this->createProducerWithUser([
            'type' => ProducerType::Agency,
            'agency_name' => 'Test Agency',
            'bio' => 'Agency bio',
        ]);

        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->actingAs($faceUser)
            ->getJson("/api/v1/public/producers/{$producer->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $producer->id)
            ->assertJsonPath('data.type', 'agency')
            ->assertJsonPath('data.display_name', 'Test Agency');
    }

    public function test_response_includes_all_expected_fields_for_agency_producer(): void
    {
        $producer = $this->createProducerWithUser([
            'type' => ProducerType::Agency,
            'agency_name' => 'My Agency',
            'first_name' => null,
            'last_name' => null,
            'bio' => 'We are a creative agency',
            'profile_photo' => 'agency-photo.jpg',
            'agency_logo' => 'agency-logo.png',
        ]);

        $response = $this->getJson("/api/v1/public/producers/{$producer->id}");

        $response->assertOk()
            ->assertJsonPath('data.type', 'agency')
            ->assertJsonPath('data.agency_name', 'My Agency')
            ->assertJsonPath('data.display_name', 'My Agency')
            ->assertJsonPath('data.bio', 'We are a creative agency')
            ->assertJsonPath('data.missions_count', 0)
            ->assertJsonPath('data.average_rating', null)
            ->assertJsonPath('data.ratings_count', 0);

        // Agency should have logo URL
        $this->assertNotNull($response->json('data.agency_logo_url'));
    }

    public function test_response_includes_all_expected_fields_for_particulier_producer(): void
    {
        $producer = $this->createProducerWithUser([
            'type' => ProducerType::Particulier,
            'agency_name' => null,
            'first_name' => 'Marie',
            'last_name' => 'Martin',
            'bio' => 'Independent producer',
            'profile_photo' => 'marie-photo.jpg',
            'agency_logo' => null,
        ]);

        $response = $this->getJson("/api/v1/public/producers/{$producer->id}");

        $response->assertOk()
            ->assertJsonPath('data.type', 'particulier')
            ->assertJsonPath('data.agency_name', null)
            ->assertJsonPath('data.display_name', 'Marie Martin')
            ->assertJsonPath('data.bio', 'Independent producer')
            ->assertJsonPath('data.missions_count', 0)
            ->assertJsonPath('data.average_rating', null)
            ->assertJsonPath('data.ratings_count', 0)
            // Particulier should have no logo
            ->assertJsonPath('data.agency_logo_url', null)
            ->assertJsonPath('data.agency_logo_thumbnail_url', null);
    }

    public function test_agency_producer_has_agency_logo_url_particulier_has_null(): void
    {
        $agency = $this->createProducerWithUser([
            'type' => ProducerType::Agency,
            'agency_name' => 'Logo Agency',
            'agency_logo' => 'test-logo.png',
            'agency_logo_thumbnail' => 'test-logo-thumb.png',
        ]);

        $particulier = $this->createProducerWithUser([
            'type' => ProducerType::Particulier,
            'first_name' => 'Pierre',
            'last_name' => 'Durand',
            'agency_logo' => null,
        ]);

        // Agency has logo
        $agencyResponse = $this->getJson("/api/v1/public/producers/{$agency->id}");
        $agencyResponse->assertOk();
        $this->assertNotNull($agencyResponse->json('data.agency_logo_url'));
        $this->assertNotNull($agencyResponse->json('data.agency_logo_thumbnail_url'));

        // Particulier has no logo
        $particulierResponse = $this->getJson("/api/v1/public/producers/{$particulier->id}");
        $particulierResponse->assertOk();
        $this->assertNull($particulierResponse->json('data.agency_logo_url'));
        $this->assertNull($particulierResponse->json('data.agency_logo_thumbnail_url'));
    }

    public function test_non_existent_producer_returns_404(): void
    {
        $nonExistentId = 99999;

        $response = $this->getJson("/api/v1/public/producers/{$nonExistentId}");

        $response->assertNotFound();
    }

    public function test_producer_with_no_missions_shows_missions_count_zero(): void
    {
        $producer = $this->createProducerWithUser();

        $response = $this->getJson("/api/v1/public/producers/{$producer->id}");

        $response->assertOk()
            ->assertJsonPath('data.missions_count', 0);
    }

    public function test_producer_with_published_missions_shows_correct_count(): void
    {
        $producer = $this->createProducerWithUser();

        // Create 3 published missions
        Mission::factory()->count(3)->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Published,
        ]);

        $response = $this->getJson("/api/v1/public/producers/{$producer->id}");

        $response->assertOk()
            ->assertJsonPath('data.missions_count', 3);
    }

    public function test_producer_with_mixed_status_missions_only_counts_published(): void
    {
        $producer = $this->createProducerWithUser();

        // Create 2 published missions
        Mission::factory()->count(2)->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Published,
        ]);

        // Create 1 draft mission (should not count)
        Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Draft,
        ]);

        // Create 1 closed mission (should not count)
        Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Closed,
        ]);

        // Create 1 completed mission (should not count)
        Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Completed,
        ]);

        $response = $this->getJson("/api/v1/public/producers/{$producer->id}");

        $response->assertOk()
            ->assertJsonPath('data.missions_count', 2);
    }

    public function test_producer_with_only_non_published_missions_shows_zero(): void
    {
        $producer = $this->createProducerWithUser();

        // Create missions in non-published statuses only
        Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Draft,
        ]);

        Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Closed,
        ]);

        Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Completed,
        ]);

        $response = $this->getJson("/api/v1/public/producers/{$producer->id}");

        $response->assertOk()
            ->assertJsonPath('data.missions_count', 0);
    }

    public function test_response_includes_average_rating_null_for_mvp(): void
    {
        $producer = $this->createProducerWithUser();

        $response = $this->getJson("/api/v1/public/producers/{$producer->id}");

        $response->assertOk()
            ->assertJsonPath('data.average_rating', null)
            ->assertJsonPath('data.ratings_count', 0);
    }

    public function test_response_includes_member_since_in_french_format(): void
    {
        $producer = $this->createProducerWithUser();

        $response = $this->getJson("/api/v1/public/producers/{$producer->id}");

        $response->assertOk();

        // member_since should be a string in French format (e.g., "janvier 2026")
        $memberSince = $response->json('data.member_since');
        $this->assertIsString($memberSince);
        $this->assertNotEmpty($memberSince);

        // Verify it contains a French month name
        $frenchMonths = [
            'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
            'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
        ];

        $containsFrenchMonth = false;
        foreach ($frenchMonths as $month) {
            if (stripos($memberSince, $month) !== false) {
                $containsFrenchMonth = true;
                break;
            }
        }

        $this->assertTrue($containsFrenchMonth, "member_since '{$memberSince}' should contain a French month name");
    }

    public function test_invalid_producer_id_format_returns_404(): void
    {
        // Non-numeric ID should not match the route
        $response = $this->getJson('/api/v1/public/producers/invalid');

        $response->assertNotFound();
    }
}
