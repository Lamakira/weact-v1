<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\CandidatureStatus;
use App\Enums\FaceCategory;
use App\Models\Admin;
use App\Models\Candidature;
use App\Models\Experience;
use App\Models\Face;
use App\Models\FacePhoto;
use App\Models\FaceSubscription;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFaceCrudTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
        $this->adminToken = $this->admin->createToken('admin-token')->plainTextToken;
    }

    // ─── INDEX (LIST) ─────────────────────────────────────────────

    public function test_returns_paginated_list_of_faces(): void
    {
        Face::factory()->count(3)->create();

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/faces');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'nom', 'prenom', 'username', 'is_available', 'categories', 'profile_completion_percentage', 'created_at']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 15);
    }

    public function test_search_by_name_returns_filtered_results(): void
    {
        Face::factory()->create(['nom' => 'Dupont', 'prenom' => 'Jean']);
        Face::factory()->create(['nom' => 'Martin', 'prenom' => 'Paul']);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/faces?search=Dupont');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.nom', 'Dupont');
    }

    public function test_search_by_username_returns_filtered_results(): void
    {
        Face::factory()->create(['username' => 'jdupont']);
        Face::factory()->create(['username' => 'pmartin']);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/faces?search=jdupont');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.username', 'jdupont');
    }

    public function test_search_with_trailing_backslash_finds_literal_backslash_username(): void
    {
        // Backslash littéral : « \ » doit être échappé AVANT les wildcards —
        // sans ça, « back\ » devient le pattern '%back\%' où '\%' est un %
        // LITTÉRAL, et le username contenant réellement « back\ » devient
        // introuvable.
        Face::factory()->create(['username' => 'back\\slash']);
        Face::factory()->create(['username' => 'backslash']);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/faces?search='.urlencode('back\\'));

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.username', 'back\\slash');
    }

    public function test_search_by_email_returns_filtered_results(): void
    {
        $face1 = Face::factory()->create();
        User::factory()->create([
            'email' => 'unique-search-test@example.com',
            'userable_type' => Face::class,
            'userable_id' => $face1->id,
        ]);
        $face2 = Face::factory()->create();
        User::factory()->create([
            'email' => 'other@example.com',
            'userable_type' => Face::class,
            'userable_id' => $face2->id,
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/faces?search=unique-search-test');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_filter_by_category_returns_correct_faces(): void
    {
        Face::factory()->create(['categories' => [FaceCategory::ACTEUR->value]]);
        Face::factory()->create(['categories' => [FaceCategory::MANNEQUIN->value]]);
        Face::factory()->create(['categories' => [FaceCategory::ACTEUR->value]]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/faces?category=acteur');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_filter_by_availability_returns_correct_faces(): void
    {
        Face::factory()->create(['is_available' => true]);
        Face::factory()->create(['is_available' => false]);
        Face::factory()->create(['is_available' => true]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/faces?is_available=1');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    // ─── SHOW ─────────────────────────────────────────────────────

    public function test_show_returns_single_face_with_full_detail(): void
    {
        $face = Face::factory()->create([
            'nom' => 'Doe',
            'prenom' => 'Jane',
            'bio' => 'Test bio',
            'whatsapp_number' => '+22997000000',
            'categories' => [FaceCategory::ACTEUR->value],
            'is_featured' => true,
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson("/api/v1/admin/faces/{$face->uuid}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'nom', 'prenom', 'username', 'bio', 'categories', 'is_available', 'profile_completion_percentage', 'average_rating', 'ratings_count'],
                'message',
            ])
            ->assertJsonPath('data.nom', 'Doe')
            ->assertJsonPath('data.prenom', 'Jane')
            ->assertJsonPath('data.bio', 'Test bio')
            ->assertJsonPath('data.whatsapp_number', '+22997000000')
            ->assertJsonPath('data.is_featured', true)
            ->assertJsonPath('data.categories.0.value', 'acteur');
    }

    public function test_show_returns_404_for_nonexistent_face(): void
    {
        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/faces/00000000-0000-0000-0000-000000000000');

        $response->assertNotFound();
    }

    // ─── SHOW DETAIL (Story 13-7) ────────────────────────────────

    public function test_show_face_includes_photos_and_experiences(): void
    {
        $face = Face::factory()->create();
        FacePhoto::factory()->create(['face_id' => $face->id, 'position' => 1]);
        FacePhoto::factory()->create(['face_id' => $face->id, 'position' => 2]);
        Experience::factory()->create([
            'face_id' => $face->id,
            'titre' => 'Pub TV Bénin',
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson("/api/v1/admin/faces/{$face->uuid}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'photos' => [['id', 'photo_url', 'thumbnail_url', 'position']],
                    'experiences' => [['id', 'titre', 'description', 'date_debut', 'date_fin', 'is_ongoing', 'formatted_period']],
                    'photos_count',
                    'experiences_count',
                ],
            ])
            ->assertJsonCount(2, 'data.photos')
            ->assertJsonCount(1, 'data.experiences')
            ->assertJsonPath('data.photos_count', 2)
            ->assertJsonPath('data.experiences_count', 1)
            ->assertJsonPath('data.experiences.0.titre', 'Pub TV Bénin');
    }

    public function test_show_face_includes_video_urls(): void
    {
        $face = Face::factory()->create([
            'presentation_video' => 'test-presentation.mp4',
            'presentation_video_thumbnail' => 'test-presentation-thumb.jpg',
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson("/api/v1/admin/faces/{$face->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.presentation_video_url', fn ($url) => str_contains($url, 'test-presentation.mp4'))
            ->assertJsonPath('data.presentation_video_thumbnail_url', fn ($url) => str_contains($url, 'test-presentation-thumb.jpg'));
    }

    public function test_show_face_includes_physical_and_tariffs(): void
    {
        $face = Face::factory()->create([
            'taille' => 175,
            'poids' => 68,
            'tarif_horaire' => 25000,
            'tarif_journalier' => 150000,
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson("/api/v1/admin/faces/{$face->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.taille', 175)
            ->assertJsonPath('data.poids', 68)
            ->assertJsonPath('data.tarif_horaire', 25000)
            ->assertJsonPath('data.tarif_journalier', 150000)
            ->assertJsonPath('data.formatted_tarif_horaire', '25 000 XOF/demi-journée')
            ->assertJsonPath('data.formatted_tarif_journalier', '150 000 XOF/jour');
    }

    public function test_show_face_returns_empty_arrays_when_no_relations(): void
    {
        $face = Face::factory()->create();

        $response = $this->withToken($this->adminToken)
            ->getJson("/api/v1/admin/faces/{$face->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.photos', [])
            ->assertJsonPath('data.experiences', [])
            ->assertJsonPath('data.photos_count', 0)
            ->assertJsonPath('data.experiences_count', 0);
    }

    // ─── UPDATE ───────────────────────────────────────────────────

    public function test_update_face_fields_successfully(): void
    {
        $face = Face::factory()->create([
            'nom' => 'OldName',
            'bio' => 'Old bio',
            'is_featured' => false,
        ]);
        User::factory()->create([
            'email' => 'face-admin@example.com',
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        FacePhoto::factory()->create(['face_id' => $face->id, 'position' => 1]);
        Experience::factory()->create([
            'face_id' => $face->id,
            'titre' => 'Campagne test',
        ]);

        $response = $this->withToken($this->adminToken)
            ->putJson("/api/v1/admin/faces/{$face->uuid}", [
                'nom' => 'NewName',
                'bio' => 'Updated bio',
                'is_available' => false,
                'is_featured' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.nom', 'NewName')
            ->assertJsonPath('data.bio', 'Updated bio')
            ->assertJsonPath('data.is_available', false)
            ->assertJsonPath('data.is_featured', true)
            ->assertJsonPath('data.email', 'face-admin@example.com')
            ->assertJsonPath('data.photos_count', 1)
            ->assertJsonPath('data.experiences_count', 1)
            ->assertJsonCount(1, 'data.photos')
            ->assertJsonCount(1, 'data.experiences')
            ->assertJsonPath('message', 'Profil Face mis à jour avec succès');

        $this->assertDatabaseHas('faces', [
            'id' => $face->id,
            'nom' => 'NewName',
            'bio' => 'Updated bio',
            'is_featured' => true,
        ]);
    }

    public function test_update_with_invalid_data_returns_422(): void
    {
        $face = Face::factory()->create();

        $response = $this->withToken($this->adminToken)
            ->putJson("/api/v1/admin/faces/{$face->uuid}", [
                'categories' => ['invalid_category'],
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'error' => ['code', 'message', 'details'],
            ]);
    }

    public function test_update_clears_ville_when_country_changes_away_from_benin(): void
    {
        $face = Face::factory()->create([
            'pays' => 'Bénin',
            'ville' => 'Cotonou',
        ]);

        $response = $this->withToken($this->adminToken)
            ->putJson("/api/v1/admin/faces/{$face->uuid}", [
                'pays' => 'Togo',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.pays', 'Togo')
            ->assertJsonPath('data.ville', null);

        $this->assertDatabaseHas('faces', [
            'id' => $face->id,
            'pays' => 'Togo',
            'ville' => null,
        ]);
    }

    public function test_update_username_uniqueness_validation(): void
    {
        $face1 = Face::factory()->create(['username' => 'taken_username']);
        $face2 = Face::factory()->create(['username' => 'my_username']);

        $response = $this->withToken($this->adminToken)
            ->putJson("/api/v1/admin/faces/{$face2->uuid}", [
                'username' => 'taken_username',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.details.username.0', "Ce nom d'utilisateur est déjà utilisé.");
    }

    public function test_update_username_allows_keeping_own_username(): void
    {
        $face = Face::factory()->create(['username' => 'my_username']);

        $response = $this->withToken($this->adminToken)
            ->putJson("/api/v1/admin/faces/{$face->uuid}", [
                'username' => 'my_username',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.username', 'my_username');
    }

    // ─── DESTROY ──────────────────────────────────────────────────

    public function test_delete_face_and_associated_user(): void
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $response = $this->withToken($this->adminToken)
            ->deleteJson("/api/v1/admin/faces/{$face->uuid}");

        $response->assertOk()
            ->assertJsonPath('message', 'Profil Face supprimé avec succès');

        $this->assertDatabaseMissing('faces', ['id' => $face->id]);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_delete_blocked_by_active_candidatures(): void
    {
        $face = Face::factory()->create();
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
        $mission = Mission::factory()->create(['producer_id' => $producer->id]);

        Candidature::factory()->create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Accepted,
        ]);

        $response = $this->withToken($this->adminToken)
            ->deleteJson("/api/v1/admin/faces/{$face->uuid}");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'active_candidatures')
            ->assertJsonPath('error.message', 'Impossible de supprimer ce profil. Des candidatures actives existent.');

        $this->assertDatabaseHas('faces', ['id' => $face->id]);
    }

    public function test_delete_allowed_with_completed_candidatures_only(): void
    {
        $face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $producer = Producer::factory()->create();
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
        $mission = Mission::factory()->create(['producer_id' => $producer->id]);

        Candidature::factory()->create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Completed,
        ]);

        $response = $this->withToken($this->adminToken)
            ->deleteJson("/api/v1/admin/faces/{$face->uuid}");

        $response->assertOk();
        $this->assertDatabaseMissing('faces', ['id' => $face->id]);
    }

    // ─── AUTH GUARDS ──────────────────────────────────────────────

    public function test_returns_401_for_unauthenticated_request(): void
    {
        $response = $this->getJson('/api/v1/admin/faces');
        $response->assertUnauthorized();
    }

    public function test_returns_403_for_non_admin_user(): void
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/admin/faces');

        $response->assertForbidden();
    }

    // ─── Subscription-driven Featured (FEATURE-FP-1.6) ────────────

    public function test_admin_show_exposes_subscription_tier_for_active_subscriber(): void
    {
        $face = Face::factory()->create(['is_featured' => false]);
        FaceSubscription::factory()->active()->create(['face_id' => $face->id]);

        $response = $this->withToken($this->adminToken)
            ->getJson("/api/v1/admin/faces/{$face->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.is_featured', false)
            ->assertJsonPath('data.subscription_tier', 'pro');

        $this->assertArrayNotHasKey('is_featured_by_subscription', $response->json('data'));
    }

    public function test_admin_show_exposes_free_subscription_tier_when_no_active_subscription(): void
    {
        $face = Face::factory()->create(['is_featured' => false]);
        FaceSubscription::factory()->expired()->create(['face_id' => $face->id]);

        $response = $this->withToken($this->adminToken)
            ->getJson("/api/v1/admin/faces/{$face->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.is_featured', false)
            ->assertJsonPath('data.subscription_tier', 'free');
    }

    public function test_admin_show_distinguishes_manual_featured_from_subscription_featured(): void
    {
        $manualOnly = Face::factory()->create(['is_featured' => true]);

        $subscriptionOnly = Face::factory()->create(['is_featured' => false]);
        FaceSubscription::factory()->active()->create(['face_id' => $subscriptionOnly->id]);

        $bothFlags = Face::factory()->create(['is_featured' => true]);
        FaceSubscription::factory()->active()->create(['face_id' => $bothFlags->id]);

        $manualResponse = $this->withToken($this->adminToken)
            ->getJson("/api/v1/admin/faces/{$manualOnly->uuid}");
        $manualResponse->assertOk()
            ->assertJsonPath('data.is_featured', true)
            ->assertJsonPath('data.subscription_tier', 'free');

        $subscriptionResponse = $this->withToken($this->adminToken)
            ->getJson("/api/v1/admin/faces/{$subscriptionOnly->uuid}");
        $subscriptionResponse->assertOk()
            ->assertJsonPath('data.is_featured', false)
            ->assertJsonPath('data.subscription_tier', 'pro');

        $bothResponse = $this->withToken($this->adminToken)
            ->getJson("/api/v1/admin/faces/{$bothFlags->uuid}");
        $bothResponse->assertOk()
            ->assertJsonPath('data.is_featured', true)
            ->assertJsonPath('data.subscription_tier', 'pro');
    }

    public function test_admin_index_exposes_subscription_tier_for_each_face(): void
    {
        $activeSubscriber = Face::factory()->create([
            'prenom' => 'Active Subscriber',
            'created_at' => now()->subHour(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $activeSubscriber->id]);
        FaceSubscription::factory()->active()->create(['face_id' => $activeSubscriber->id]);

        $freeFace = Face::factory()->create([
            'prenom' => 'Free Face',
            'created_at' => now()->subDay(),
        ]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $freeFace->id]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/faces');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $activeSubscriber->uuid)
            ->assertJsonPath('data.0.subscription_tier', 'pro')
            ->assertJsonPath('data.1.id', $freeFace->uuid)
            ->assertJsonPath('data.1.subscription_tier', 'free');
    }

    public function test_producer_view_does_not_expose_subscription_tier(): void
    {
        $face = Face::factory()->create(['is_featured' => false]);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);
        FaceSubscription::factory()->active()->create(['face_id' => $face->id]);

        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
        $producerToken = $producerUser->createToken('producer-token')->plainTextToken;

        $response = $this->withToken($producerToken)
            ->getJson("/api/v1/producer/candidates/{$face->uuid}");

        $response->assertOk();
        $body = $response->json('data');
        $this->assertArrayHasKey('is_featured', $body);
        $this->assertArrayNotHasKey('is_featured_by_subscription', $body);
        $this->assertArrayNotHasKey('subscription_tier', $body);
    }

    public function test_face_owner_profile_does_not_expose_subscription_tier(): void
    {
        $face = Face::factory()->create(['is_featured' => false]);
        FaceSubscription::factory()->active()->create(['face_id' => $face->id]);

        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $faceToken = $faceUser->createToken('face-token')->plainTextToken;

        $response = $this->withToken($faceToken)
            ->getJson('/api/v1/face/profile');

        $response->assertOk();
        $body = $response->json('data');
        $this->assertArrayHasKey('is_featured', $body);
        $this->assertArrayNotHasKey('is_featured_by_subscription', $body);
        $this->assertArrayNotHasKey('subscription_tier', $body);
    }

    public function test_admin_show_exposes_starter_subscription_tier(): void
    {
        $face = Face::factory()->create(['is_featured' => false]);
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $face->id]);

        $response = $this->withToken($this->adminToken)
            ->getJson("/api/v1/admin/faces/{$face->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.subscription_tier', 'starter');
    }

    public function test_admin_show_exposes_elite_subscription_tier(): void
    {
        $face = Face::factory()->create(['is_featured' => false]);
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $face->id]);

        $response = $this->withToken($this->adminToken)
            ->getJson("/api/v1/admin/faces/{$face->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.subscription_tier', 'elite');
    }
}
