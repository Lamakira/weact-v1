<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\Admin;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Lot B13 — un Producer connecté (même sans abonnement) voit le nom complet des Faces sur
 * /v1/public/faces et /v1/public/faces/{username}. Sécurité : un invité (ou toute autre
 * session : Face, admin, jeton invalide ou expiré, Producer désactivé) ne reçoit JAMAIS le
 * nom complet, y compris quand une réponse Producer est déjà dans le cache public.
 */
class ProducerFullNameVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Face $face;

    protected function setUp(): void
    {
        parent::setUp();

        // Cache public ACTIF (désactivé par défaut en test) : c'est lui qu'on éprouve.
        config([
            'cache.public_response_ttl' => 60,
            'cache.public_response_store' => 'array',
        ]);
        Cache::store('array')->flush();

        $this->face = Face::factory()->create([
            'prenom' => 'Adjoua',
            'nom' => 'Kouassi',
            'username' => 'adjoua-k',
        ]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    private function producerToken(bool $active = true): string
    {
        $user = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => Producer::factory()->create()->id,
            'is_active' => $active,
        ]);

        return $user->createToken('test')->plainTextToken;
    }

    /**
     * @return array<string, string>
     */
    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    private function listing(array $headers = []): TestResponse
    {
        return $this->getJson('/api/v1/public/faces', $headers);
    }

    private function profile(array $headers = []): TestResponse
    {
        return $this->getJson('/api/v1/public/faces/adjoua-k', $headers);
    }

    private function assertNoFullName(TestResponse $response): void
    {
        $response->assertOk();
        $this->assertStringNotContainsString('Kouassi', (string) $response->getContent());
    }

    // --- Ce que voit chacun ---

    public function test_a_guest_sees_only_the_initial(): void
    {
        $listing = $this->listing();
        $this->assertNoFullName($listing);
        $listing->assertJsonPath('data.0.nom', 'K.')
            ->assertJsonPath('data.0.display_name', 'Adjoua K.');

        $profile = $this->profile();
        $this->assertNoFullName($profile);
        $profile->assertJsonPath('data.display_name', 'Adjoua K.')
            ->assertJsonMissingPath('data.nom');
    }

    public function test_a_logged_in_producer_sees_the_full_name_without_any_subscription(): void
    {
        $headers = $this->bearer($this->producerToken());

        $this->listing($headers)->assertOk()
            ->assertJsonPath('data.0.nom', 'Kouassi')
            ->assertJsonPath('data.0.display_name', 'Adjoua Kouassi');

        $this->profile($headers)->assertOk()
            ->assertJsonPath('data.nom', 'Kouassi')
            ->assertJsonPath('data.display_name', 'Adjoua Kouassi');
    }

    public function test_producer_responses_are_never_storable_by_a_shared_http_cache(): void
    {
        $response = $this->profile($this->bearer($this->producerToken()));

        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('Authorization', (string) $response->headers->get('Vary'));
        $this->assertStringContainsString('Authorization', (string) $this->profile()->headers->get('Vary'));
    }

    // --- Cache public : jamais de fuite vers un invité ---

    public function test_a_cached_producer_listing_is_never_served_to_a_guest(): void
    {
        $this->listing($this->bearer($this->producerToken()))
            ->assertHeader('X-Public-Cache', 'MISS')
            ->assertJsonPath('data.0.nom', 'Kouassi');

        $guest = $this->listing();
        $guest->assertHeader('X-Public-Cache', 'MISS');
        $this->assertNoFullName($guest);

        $guestAgain = $this->listing();
        $guestAgain->assertHeader('X-Public-Cache', 'HIT');
        $this->assertNoFullName($guestAgain);
    }

    public function test_a_cached_producer_profile_is_never_served_to_a_guest(): void
    {
        $this->profile($this->bearer($this->producerToken()))->assertJsonPath('data.nom', 'Kouassi');

        $this->assertNoFullName($this->profile());
        $this->assertNoFullName($this->profile());
    }

    public function test_a_cached_guest_listing_is_not_served_to_a_producer(): void
    {
        $this->listing()->assertHeader('X-Public-Cache', 'MISS');

        $this->listing($this->bearer($this->producerToken()))
            ->assertHeader('X-Public-Cache', 'MISS')
            ->assertJsonPath('data.0.nom', 'Kouassi');
    }

    public function test_producers_share_one_cache_entry_and_guests_another(): void
    {
        $this->listing($this->bearer($this->producerToken()))->assertHeader('X-Public-Cache', 'MISS');
        $this->listing($this->bearer($this->producerToken()))
            ->assertHeader('X-Public-Cache', 'HIT')
            ->assertJsonPath('data.0.nom', 'Kouassi');
    }

    // --- Toute autre session reste un invité ---

    public function test_a_face_session_is_a_guest(): void
    {
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => Face::factory()->create()->id,
        ]);
        $headers = $this->bearer($faceUser->createToken('test')->plainTextToken);

        $this->assertNoFullName($this->listing($headers));
        $this->assertNoFullName($this->profile($headers));
    }

    public function test_an_admin_token_is_a_guest(): void
    {
        $admin = Admin::factory()->create();
        $headers = $this->bearer($admin->createToken('test', [Admin::ABILITY_TWO_FACTOR])->plainTextToken);

        $this->assertNoFullName($this->listing($headers));
        $this->assertNoFullName($this->profile($headers));
    }

    public function test_an_invalid_token_is_a_guest_and_not_a_401(): void
    {
        $headers = $this->bearer('999|not-a-real-token');

        $this->assertNoFullName($this->listing($headers));
        $this->assertNoFullName($this->profile($headers));
    }

    public function test_an_expired_token_is_a_guest(): void
    {
        config(['sanctum.expiration' => 60]);
        $token = $this->producerToken();
        $this->travel(2)->hours();

        $this->assertNoFullName($this->listing($this->bearer($token)));
    }

    public function test_a_deactivated_producer_is_a_guest(): void
    {
        $this->assertNoFullName($this->listing($this->bearer($this->producerToken(active: false))));
    }

    public function test_an_invalid_token_never_reads_a_cached_producer_response(): void
    {
        $this->listing($this->bearer($this->producerToken()))->assertJsonPath('data.0.nom', 'Kouassi');

        $this->assertNoFullName($this->listing($this->bearer('1|forged')));
    }
}
