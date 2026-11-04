<?php

declare(strict_types=1);

namespace Tests\Feature\ImageContract;

use App\Events\MissionPublished;
use App\Models\Mission;
use App\Models\MissionImageTerms;
use App\Models\SignatureChallenge;
use App\Services\ImageContract\SignatureChallengeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\ImageContract\Concerns\BuildsImageContracts;
use Tests\TestCase;

class PublishMissionWithImageContractTest extends TestCase
{
    use BuildsImageContracts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('public');
        $this->setUpParties();
    }

    private function purpose(): string
    {
        return SignatureChallengeService::PURPOSE_MISSION_PUBLISH;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function classicPayload(array $overrides = []): array
    {
        return array_merge([
            'titre' => 'Recherche visages pour publicité lait',
            'description' => 'Nous recherchons des visages pour une publicité nationale.',
            'date_tournage' => now()->addMonth()->format('Y-m-d'),
            'profil_recherche' => 'Femmes 25-35 ans, bonne élocution',
            'budget' => 150000,
            'date_limite_candidature' => now()->addWeeks(2)->format('Y-m-d'),
            'nombre_faces_voulu' => 2,
            'type_mission' => 'publicite',
            'genre_voulu' => 'femme',
            'lieu' => 'Cotonou, Bénin',
            'duree' => '1 journée',
            'image_terms' => $this->terms(),
            'signature' => ['name' => 'Jean Producteur', 'code' => '123456'],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function ugcPayload(string $compensation = 'product', array $overrides = []): array
    {
        return array_merge([
            'titre' => 'Appel UGC Unboxing Tenue Shade Fit',
            'description' => 'Nous cherchons des créatrices pour un unboxing et un avis.',
            'profil_recherche' => 'Créatrices lifestyle',
            'date_limite_candidature' => now()->addWeeks(2)->format('Y-m-d'),
            'nombre_faces_voulu' => 3,
            'type_mission' => 'ugc',
            'genre_voulu' => 'femme',
            'type_compensation' => $compensation,
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000,
            'product_photos' => [UploadedFile::fake()->image('produit.jpg', 900, 900)],
            'image_terms' => $this->terms(),
            'signature' => ['name' => 'Jean Producteur', 'code' => '123456'],
        ] + ($compensation === 'hybrid' ? ['nombre_videos' => 3, 'montant_remuneration' => 15000] : []), $overrides);
    }

    private function postMission(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->producerUser)
            ->withHeaders(['User-Agent' => 'TestBrowser/1.0'])
            ->postJson('/api/v1/producer/missions', $payload);
    }

    public function test_a_classic_mission_is_published_with_signed_terms(): void
    {
        Event::fake([MissionPublished::class]);
        $challenge = $this->makeChallenge($this->producerUser, $this->purpose(), '123456');

        $response = $this->postMission($this->classicPayload());

        $response->assertCreated()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.image_terms.finalite', 'Campagne réseaux sociaux de la marque')
            ->assertJsonPath('data.image_terms.supports.0.label', 'Réseaux sociaux')
            ->assertJsonPath('data.image_terms.territoire.label', 'Bénin')
            ->assertJsonPath('data.image_terms.duree_annees', 3)
            ->assertJsonPath('data.image_terms.producer_signed_name', 'Jean Producteur')
            ->assertJsonMissingPath('data.image_terms.requires_guardian');

        $mission = Mission::firstOrFail();
        $terms = MissionImageTerms::firstOrFail();
        $this->assertSame($mission->id, $terms->mission_id);
        $this->assertSame('v1', $terms->template_version);
        $this->assertSame(['social', 'web'], $terms->supports);
        $this->assertSame('p•••••@example.com', $terms->producer_signed_email_masked);
        $this->assertSame('TestBrowser/1.0', $terms->producer_user_agent);
        $this->assertNotNull($challenge->fresh()->consumed_at);
        Event::assertDispatched(MissionPublished::class);

        // Ni e-mail complet ni user agent dans la réponse.
        $body = $response->getContent();
        $this->assertStringNotContainsString('producteur@example.com', (string) $body);
        $this->assertStringNotContainsString('TestBrowser', (string) $body);
        $this->assertSame(0, \App\Models\ImageContract::count());
    }

    public function test_duplicate_supports_are_refused(): void
    {
        $this->makeChallenge($this->producerUser, $this->purpose(), '123456');

        $this->postMission($this->classicPayload(['image_terms' => $this->terms(['supports' => ['web', 'web', 'social']])]))
            ->assertUnprocessable();

        $this->assertSame(0, Mission::count());
    }

    public function test_a_ugc_product_only_mission_is_pending_payment_and_signed_at_creation(): void
    {
        $this->makeChallenge($this->producerUser, $this->purpose(), '123456');

        $this->postMission($this->ugcPayload('product'))
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.image_terms.duree_annees', 3);

        $this->assertSame('pending_payment', Mission::firstOrFail()->status->value);
        $this->assertSame(1, MissionImageTerms::count());
    }

    public function test_a_ugc_hybrid_mission_is_published_with_terms(): void
    {
        Event::fake([MissionPublished::class]);
        $this->makeChallenge($this->producerUser, $this->purpose(), '123456');

        $this->postMission($this->ugcPayload('hybrid'))
            ->assertCreated()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.image_terms.territoire.value', 'benin');

        $this->assertSame(1, MissionImageTerms::count());
        Event::assertDispatched(MissionPublished::class);
    }

    public function test_a_multipart_ugc_publication_carries_the_terms(): void
    {
        $this->makeChallenge($this->producerUser, $this->purpose(), '123456');
        $payload = $this->ugcPayload('product');
        $payload['image_terms'] = [
            'finalite' => 'Campagne réseaux sociaux de la marque',
            'supports' => ['social', 'web'],
            'territoire' => 'benin',
            'duree_annees' => '3',
        ];

        $this->actingAs($this->producerUser)
            ->post('/api/v1/producer/missions', $payload, ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.image_terms.supports.1.value', 'web')
            ->assertJsonPath('data.image_terms.duree_annees', 3);

        $this->assertSame(['social', 'web'], MissionImageTerms::firstOrFail()->supports);
        $this->assertSame(1, \App\Models\ProductPhoto::count());
    }

    public function test_all_supports_is_stored_alone(): void
    {
        $this->makeChallenge($this->producerUser, $this->purpose(), '123456');

        $this->postMission($this->classicPayload(['image_terms' => $this->terms(['supports' => ['all']])]))
            ->assertCreated();

        $this->assertSame(['all'], MissionImageTerms::firstOrFail()->supports);
    }

    public function test_a_wrong_code_leaves_no_mission_no_terms_no_event_and_persists_the_attempt(): void
    {
        Event::fake([MissionPublished::class]);
        $challenge = $this->makeChallenge($this->producerUser, $this->purpose(), '123456');

        $this->postMission($this->classicPayload(['signature' => ['name' => 'Jean Producteur', 'code' => '000000']]))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SIGNATURE_CODE_INVALID');

        $this->assertSame(0, Mission::count());
        $this->assertSame(0, MissionImageTerms::count());
        Event::assertNotDispatched(MissionPublished::class);
        $this->assertSame(1, $challenge->fresh()->attempts);
        $this->assertNull($challenge->fresh()->consumed_at);
    }

    public function test_a_wrong_code_on_a_ugc_mission_stores_no_photo(): void
    {
        Event::fake([MissionPublished::class]);
        $this->makeChallenge($this->producerUser, $this->purpose(), '123456');

        $this->postMission($this->ugcPayload('hybrid', ['signature' => ['name' => 'Jean Producteur', 'code' => '999999']]))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SIGNATURE_CODE_INVALID');

        $this->assertSame(0, Mission::count());
        $this->assertSame(0, MissionImageTerms::count());
        $this->assertSame(0, \App\Models\ProductPhoto::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
        Event::assertNotDispatched(MissionPublished::class);
    }

    public function test_an_expired_code_creates_nothing(): void
    {
        $this->makeChallenge($this->producerUser, $this->purpose(), '123456', ['expires_at' => now()->subMinute()]);

        $this->postMission($this->classicPayload())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SIGNATURE_CODE_EXPIRED');

        $this->assertSame(0, Mission::count());
    }

    public function test_a_consumed_code_cannot_be_used_twice(): void
    {
        $this->makeChallenge($this->producerUser, $this->purpose(), '123456');

        $this->postMission($this->classicPayload())->assertCreated();
        $this->postMission($this->classicPayload())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SIGNATURE_CODE_INVALID');

        $this->assertSame(1, Mission::count());
        $this->assertSame(1, MissionImageTerms::count());
    }

    public function test_the_sixth_attempt_is_refused_even_with_the_right_code(): void
    {
        $this->makeChallenge($this->producerUser, $this->purpose(), '123456');
        $wrong = $this->classicPayload(['signature' => ['name' => 'Jean Producteur', 'code' => '000000']]);

        for ($i = 0; $i < 5; $i++) {
            $this->postMission($wrong)->assertJsonPath('error.code', 'SIGNATURE_CODE_INVALID');
        }

        $this->postMission($this->classicPayload())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SIGNATURE_CODE_TOO_MANY_ATTEMPTS');

        $this->assertSame(0, Mission::count());
    }

    public function test_a_booking_request_code_does_not_publish_a_mission(): void
    {
        $this->makeChallenge($this->producerUser, SignatureChallengeService::PURPOSE_BOOKING_REQUEST, '123456');

        $this->postMission($this->classicPayload())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SIGNATURE_CODE_INVALID');

        $this->assertSame(0, Mission::count());
    }

    public function test_conditions_and_signature_are_required_when_enabled(): void
    {
        $payload = $this->classicPayload();
        unset($payload['image_terms'], $payload['signature']);

        $this->postMission($payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image_terms', 'signature']);

        $this->assertSame(0, Mission::count());
        $this->assertSame(0, SignatureChallenge::count());
    }

    public function test_invalid_terms_are_refused(): void
    {
        $this->makeChallenge($this->producerUser, $this->purpose(), '123456');

        $this->postMission($this->classicPayload(['image_terms' => $this->terms(['supports' => ['all', 'web'], 'duree_annees' => 7])]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image_terms.supports', 'image_terms.duree_annees']);

        $this->assertSame(0, Mission::count());
    }

    public function test_an_update_cannot_change_the_terms(): void
    {
        $this->makeChallenge($this->producerUser, $this->purpose(), '123456');
        $this->postMission($this->classicPayload())->assertCreated();
        $mission = Mission::firstOrFail();
        $before = MissionImageTerms::firstOrFail()->only(['finalite', 'supports', 'territoire', 'duree_annees', 'producer_signed_name']);

        $update = $this->classicPayload([
            'titre' => 'Titre modifié',
            'image_terms' => $this->terms(['finalite' => 'Autre finalité', 'supports' => ['all'], 'territoire' => 'monde', 'duree_annees' => 10]),
            'signature' => ['name' => 'Autre Nom', 'code' => '123456'],
        ]);

        $this->actingAs($this->producerUser)
            ->putJson("/api/v1/producer/missions/{$mission->uuid}", $update)
            ->assertOk();

        $this->assertSame('Titre modifié', $mission->fresh()->titre);
        $this->assertSame($before, MissionImageTerms::firstOrFail()->only(['finalite', 'supports', 'territoire', 'duree_annees', 'producer_signed_name']));
        $this->assertSame(1, MissionImageTerms::count());
    }

    public function test_the_producer_and_face_detail_expose_the_terms_but_the_public_page_and_lists_do_not(): void
    {
        $this->makeChallenge($this->producerUser, $this->purpose(), '123456');
        $this->postMission($this->classicPayload(['date_limite_candidature' => now()->addWeeks(2)->format('Y-m-d')]))->assertCreated();
        $mission = Mission::firstOrFail();

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$mission->uuid}")
            ->assertOk()
            ->assertJsonPath('data.image_terms.finalite', 'Campagne réseaux sociaux de la marque')
            ->assertJsonMissingPath('data.image_terms.requires_guardian');

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$mission->uuid}")
            ->assertOk()
            ->assertJsonPath('data.image_terms.producer_signed_name', 'Jean Producteur')
            ->assertJsonPath('data.image_terms.requires_guardian', false);

        $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/missions')
            ->assertOk()
            ->assertJsonPath('data.0.image_terms', null);

        $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/missions')
            ->assertOk()
            ->assertJsonPath('data.0.image_terms', null);
    }

    public function test_a_minor_face_sees_that_a_guardian_is_required(): void
    {
        $this->makeChallenge($this->producerUser, $this->purpose(), '123456');
        $this->postMission($this->classicPayload())->assertCreated();
        $mission = Mission::firstOrFail();
        $this->face->update(['date_naissance' => now()->subYears(16)->toDateString()]);

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$mission->uuid}")
            ->assertOk()
            ->assertJsonPath('data.image_terms.requires_guardian', true);
    }

    public function test_a_mission_without_terms_has_a_null_image_terms(): void
    {
        $mission = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$mission->uuid}")
            ->assertOk()
            ->assertJsonPath('data.image_terms', null);
    }

    public function test_the_public_mission_response_exposes_no_terms(): void
    {
        $mission = Mission::factory()->published()->create(['producer_id' => $this->producer->id]);
        $this->makeTerms($mission);

        $response = $this->getJson('/api/v1/public/missions');
        $response->assertOk();
        $this->assertStringNotContainsString('image_terms', (string) $response->getContent());
        $this->assertStringNotContainsString('Jean Producteur', (string) $response->getContent());
    }
}
