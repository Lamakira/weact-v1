<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Http\Resources\FaceResource;
use App\Http\Resources\ProducerResource;
use App\Http\Resources\ReviewResource;
use App\Http\Resources\UserResource;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalDataExposureTest extends TestCase
{
    use RefreshDatabase;

    private Face $face;

    private User $faceUser;

    private Producer $producer;

    private User $producerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->face = Face::factory()->create([
            'nom' => 'Kouassi',
            'prenom' => 'Aïcha',
            'show_age' => true,
            'date_naissance' => now()->subYears(25)->format('Y-m-d'),
        ]);
        $this->faceUser = User::factory()->create([
            'email' => 'face.secret@example.test',
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'email' => 'producer.secret@example.test',
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    private function booking(): Booking
    {
        return Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function render(string $resource, mixed $model, mixed $viewer): array
    {
        $request = request();
        $request->setUserResolver(fn () => $viewer);

        return (new $resource($model))->toArray($request);
    }

    // === Leak 1 : email Face / Producer ===

    public function test_producer_candidate_profile_hides_face_email(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/candidates/{$this->face->uuid}")
            ->assertOk();

        $this->assertStringNotContainsString('face.secret@example.test', $response->getContent());
        $response->assertJsonMissingPath('data.email')->assertJsonMissingPath('data.is_active');
    }

    public function test_face_resource_exposes_email_to_admin_and_owner_only(): void
    {
        $this->face->setRelation('user', $this->faceUser);

        $this->assertArrayNotHasKey('email', $this->render(FaceResource::class, $this->face, $this->producerUser));
        $this->assertArrayNotHasKey('is_active', $this->render(FaceResource::class, $this->face, $this->producerUser));
        $this->assertSame('face.secret@example.test', $this->render(FaceResource::class, $this->face, $this->faceUser)['email']);
        $this->assertSame('face.secret@example.test', $this->render(FaceResource::class, $this->face, Admin::factory()->create())['email']);
    }

    public function test_producer_resource_exposes_email_to_admin_and_owner_only(): void
    {
        $this->producer->setRelation('user', $this->producerUser);

        $this->assertArrayNotHasKey('email', $this->render(ProducerResource::class, $this->producer, $this->faceUser));
        $this->assertArrayNotHasKey('is_active', $this->render(ProducerResource::class, $this->producer, $this->faceUser));
        $this->assertSame('producer.secret@example.test', $this->render(ProducerResource::class, $this->producer, $this->producerUser)['email']);
        $this->assertSame('producer.secret@example.test', $this->render(ProducerResource::class, $this->producer, Admin::factory()->create())['email']);
    }

    public function test_resources_never_lazy_load_the_user_relation(): void
    {
        $face = Face::query()->findOrFail($this->face->id);
        $this->render(FaceResource::class, $face, $this->producerUser);
        $this->assertFalse($face->relationLoaded('user'));

        $producer = Producer::query()->findOrFail($this->producer->id);
        $this->render(ProducerResource::class, $producer, $this->faceUser);
        $this->assertFalse($producer->relationLoaded('user'));
    }

    public function test_face_mission_payload_does_not_leak_producer_email(): void
    {
        $mission = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$mission->uuid}")
            ->assertOk();

        $this->assertStringNotContainsString('producer.secret@example.test', $response->getContent());
    }

    // === Leak 2 : UserResource dans BookingResource ===

    public function test_booking_hides_counterpart_email_and_internal_ids_for_both_parties(): void
    {
        $booking = $this->booking();

        $asProducer = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")->assertOk();
        $this->assertStringNotContainsString('face.secret@example.test', $asProducer->getContent());
        $asProducer->assertJsonMissingPath('data.face.email')
            ->assertJsonMissingPath('data.face.email_verified_at')
            ->assertJsonMissingPath('data.face.email_verified')
            ->assertJsonMissingPath('data.face.userable_id');

        $asFace = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")->assertOk();
        $this->assertStringNotContainsString('producer.secret@example.test', $asFace->getContent());
        $asFace->assertJsonMissingPath('data.producer.email');
    }

    public function test_user_resource_keeps_email_for_owner_admin_and_auth_responses(): void
    {
        $own = $this->render(UserResource::class, $this->faceUser, $this->faceUser);
        $this->assertSame('face.secret@example.test', $own['email']);
        $this->assertArrayHasKey('email_verified_at', $own);

        $this->assertSame('face.secret@example.test', $this->render(UserResource::class, $this->faceUser, Admin::factory()->create())['email']);
        $this->assertSame('face.secret@example.test', UserResource::forOwner($this->faceUser)->toArray(request())['email']);
        $this->assertArrayNotHasKey('email', $this->render(UserResource::class, $this->faceUser, null));
        $this->assertArrayNotHasKey('email', $this->render(UserResource::class, $this->faceUser, $this->producerUser));
    }

    // === Leak 3 : binding par id entier ===

    public function test_integer_id_does_not_bind_but_uuid_does(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/bookings/{$booking->id}")
            ->assertNotFound();

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertOk();

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/candidates/{$this->face->id}")
            ->assertNotFound();
    }

    // === Leak 4 : user_id interne ===

    public function test_public_face_profile_does_not_expose_internal_user_id(): void
    {
        $this->getJson("/api/v1/public/faces/{$this->face->username}")
            ->assertOk()
            ->assertJsonMissingPath('data.user_id');
    }

    // === Leak 5 : nom de famille public + mineurs ===

    public function test_public_list_shows_only_initial_of_last_name(): void
    {
        $response = $this->getJson('/api/v1/public/faces')->assertOk();

        $this->assertStringNotContainsString('Kouassi', $response->getContent());
        $response->assertJsonPath('data.0.prenom', 'Aïcha')
            ->assertJsonPath('data.0.nom', 'K.')
            ->assertJsonPath('data.0.display_name', 'Aïcha K.');
    }

    public function test_public_search_still_matches_on_full_last_name(): void
    {
        $response = $this->getJson('/api/v1/public/faces?search=Kouassi')->assertOk();

        $response->assertJsonCount(1, 'data');
        $this->assertStringNotContainsString('Kouassi', $response->getContent());
    }

    public function test_public_profile_shows_only_initial_of_last_name(): void
    {
        $response = $this->getJson("/api/v1/public/faces/{$this->face->username}")->assertOk();

        $this->assertStringNotContainsString('Kouassi', $response->getContent());
        $response->assertJsonPath('data.display_name', 'Aïcha K.');
    }

    public function test_logged_in_producer_and_owner_still_see_full_last_name(): void
    {
        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/candidates/{$this->face->uuid}")
            ->assertOk()
            ->assertJsonPath('data.nom', 'Kouassi');

        $this->assertSame('Kouassi', $this->render(FaceResource::class, $this->face, $this->faceUser)['nom']);
    }

    public function test_public_review_of_producer_by_face_shows_initial_only(): void
    {
        $candidature = Candidature::factory()->completed()->create([
            'face_id' => $this->face->id,
            'mission_id' => Mission::factory()->create(['producer_id' => $this->producer->id])->id,
        ]);
        Rating::create([
            'candidature_id' => $candidature->id,
            'rater_id' => $this->faceUser->id,
            'rated_id' => $this->producer->id,
            'rated_type' => Producer::class,
            'score' => 5,
            'comment' => 'Super',
        ]);

        $response = $this->getJson("/api/v1/public/producers/{$this->producer->slug}/reviews")->assertOk();

        $this->assertStringNotContainsString('Kouassi', $response->getContent());
        $response->assertJsonPath('data.0.rater.display_name', 'Aïcha K.');
    }

    public function test_review_without_userable_never_falls_back_to_the_raters_email(): void
    {
        $review = new Rating;
        $review->setRelation('rater', $this->faceUser->setRelation('userable', null));

        $out = $this->render(ReviewResource::class, $review, null);

        $this->assertSame('Utilisateur', $out['rater']['display_name']);
    }

    public function test_minor_age_is_hidden_from_public_and_producer_even_with_show_age(): void
    {
        $this->face->update(['date_naissance' => now()->subYears(16)->format('Y-m-d'), 'show_age' => true]);

        $this->getJson("/api/v1/public/faces/{$this->face->username}")
            ->assertOk()->assertJsonPath('data.age', null);

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/candidates/{$this->face->uuid}")
            ->assertOk()->assertJsonPath('data.age', null);
    }

    public function test_minor_age_stays_visible_to_owner_and_admin(): void
    {
        $this->face->update(['date_naissance' => now()->subYears(16)->format('Y-m-d'), 'show_age' => true]);
        $face = $this->face->fresh();

        $this->assertSame(16, $this->render(FaceResource::class, $face, $this->faceUser)['age']);
        $this->assertSame(16, $this->render(FaceResource::class, $face, Admin::factory()->create())['age']);
    }

    public function test_adult_age_follows_show_age(): void
    {
        $this->getJson("/api/v1/public/faces/{$this->face->username}")->assertJsonPath('data.age', 25);
        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/candidates/{$this->face->uuid}")
            ->assertJsonPath('data.age', 25);

        $this->face->update(['show_age' => false]);

        $this->getJson("/api/v1/public/faces/{$this->face->username}")->assertJsonPath('data.age', null);
        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/candidates/{$this->face->uuid}")
            ->assertJsonPath('data.age', null);
    }

    // === Leak 6 : avis publics de comptes désactivés ===

    public function test_public_reviews_of_deactivated_accounts_are_not_found(): void
    {
        $this->getJson("/api/v1/public/faces/{$this->face->username}/reviews")->assertOk();
        $this->getJson("/api/v1/public/producers/{$this->producer->slug}/reviews")->assertOk();

        $this->faceUser->update(['is_active' => false]);
        $this->producerUser->update(['is_active' => false]);

        $this->getJson("/api/v1/public/faces/{$this->face->username}/reviews")->assertNotFound();
        $this->getJson("/api/v1/public/producers/{$this->producer->slug}/reviews")->assertNotFound();
    }
}
