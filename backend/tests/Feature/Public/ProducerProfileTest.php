<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\ProducerType;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProducerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_can_view_producer_profile(): void
    {
        $producer = Producer::factory()->create([
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
        $producer = Producer::factory()->create([
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
        $producer = Producer::factory()->create([
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
            // MVP placeholders
            ->assertJsonPath('data.missions_count', 0)
            ->assertJsonPath('data.average_rating', null)
            ->assertJsonPath('data.ratings_count', 0);

        // Agency should have logo URL
        $this->assertNotNull($response->json('data.agency_logo_url'));
    }

    public function test_response_includes_all_expected_fields_for_particulier_producer(): void
    {
        $producer = Producer::factory()->create([
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
            // MVP placeholders
            ->assertJsonPath('data.missions_count', 0)
            ->assertJsonPath('data.average_rating', null)
            ->assertJsonPath('data.ratings_count', 0)
            // Particulier should have no logo
            ->assertJsonPath('data.agency_logo_url', null)
            ->assertJsonPath('data.agency_logo_thumbnail_url', null);
    }

    public function test_agency_producer_has_agency_logo_url_particulier_has_null(): void
    {
        $agency = Producer::factory()->create([
            'type' => ProducerType::Agency,
            'agency_name' => 'Logo Agency',
            'agency_logo' => 'test-logo.png',
            'agency_logo_thumbnail' => 'test-logo-thumb.png',
        ]);

        $particulier = Producer::factory()->create([
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

    public function test_response_includes_missions_count_zero_for_mvp(): void
    {
        $producer = Producer::factory()->create();

        $response = $this->getJson("/api/v1/public/producers/{$producer->id}");

        $response->assertOk()
            ->assertJsonPath('data.missions_count', 0);
    }

    public function test_response_includes_average_rating_null_for_mvp(): void
    {
        $producer = Producer::factory()->create();

        $response = $this->getJson("/api/v1/public/producers/{$producer->id}");

        $response->assertOk()
            ->assertJsonPath('data.average_rating', null)
            ->assertJsonPath('data.ratings_count', 0);
    }

    public function test_response_includes_member_since_in_french_format(): void
    {
        $producer = Producer::factory()->create();

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
