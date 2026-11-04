<?php

declare(strict_types=1);

namespace Tests\Feature\ImageContract;

use App\Events\BookingCreated;
use App\Models\Booking;
use App\Models\FaceSubscription;
use App\Models\ImageContract;
use App\Models\SignatureChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\ImageContract\Concerns\BuildsImageContracts;
use Tests\TestCase;

class CreateBookingWithImageContractTest extends TestCase
{
    use BuildsImageContracts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('local');
        $this->setUpParties();
    }

    private function postBooking(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->producerUser)
            ->withHeaders(['User-Agent' => 'TestBrowser/1.0'])
            ->postJson('/api/v1/bookings', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function ugcPayload(string $compensation = 'product'): array
    {
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $this->face->id]);

        return $this->cashPayload([
            'date_debut' => null,
            'date_fin' => null,
            'duree_heures' => null,
            'lieu' => null,
            'type_contenu' => 'UGC',
            'type_compensation' => $compensation,
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000,
            'product_photos' => [UploadedFile::fake()->image('produit.jpg', 900, 900)],
        ] + ($compensation === 'hybrid' ? ['nombre_videos' => 3, 'montant_remuneration' => 15000] : []));
    }

    public function test_cash_booking_gets_a_contract_with_snapshot_and_producer_signature(): void
    {
        Event::fake([BookingCreated::class]);
        $this->makeChallenge($this->producerUser, 'booking_request', '123456');

        $response = $this->postBooking($this->cashPayload());

        $response->assertCreated()
            ->assertJsonPath('data.image_contract.status', 'awaiting_face')
            ->assertJsonPath('data.image_contract.finalite', 'Campagne réseaux sociaux de la marque')
            ->assertJsonPath('data.image_contract.supports.0.label', 'Réseaux sociaux')
            ->assertJsonPath('data.image_contract.territoire.label', 'Bénin')
            ->assertJsonPath('data.image_contract.duree_annees', 3)
            ->assertJsonPath('data.image_contract.producer_signed_name', 'Jean Producteur')
            ->assertJsonPath('data.image_contract.face_signed_at', null)
            ->assertJsonPath('data.image_contract.pdf_ready', false)
            ->assertJsonMissingPath('data.image_contract.requires_guardian');

        $contract = ImageContract::firstOrFail();
        $booking = Booking::firstOrFail();
        $this->assertSame($booking->id, $contract->booking_id);
        $this->assertSame('v1', $contract->template_version);
        $this->assertSame(['social', 'web'], $contract->supports);
        $this->assertSame('p•••••@example.com', $contract->producer_signed_email_masked);
        $this->assertSame('TestBrowser/1.0', $contract->producer_user_agent);
        $this->assertSame('cash', $contract->snapshot['contrepartie']['kind']);
        $this->assertSame((int) $booking->montant_face_recoit, $contract->snapshot['contrepartie']['montant_face_recoit']);
        $this->assertSame('Aïcha Bello', $contract->snapshot['parties']['face']['name']);
        $this->assertSame('Publicité', $contract->snapshot['projet']['type_contenu']);
        $this->assertNotNull(SignatureChallenge::firstOrFail()->consumed_at);

        Event::assertDispatched(BookingCreated::class);
    }

    public function test_ugc_product_and_hybrid_bookings_get_a_contract_too(): void
    {
        Event::fake([BookingCreated::class]);

        $this->makeChallenge($this->producerUser, 'booking_request', '123456');
        $this->postBooking($this->ugcPayload('product'))->assertCreated();

        $this->makeChallenge($this->producerUser, 'booking_request', '123456');
        $this->postBooking($this->ugcPayload('hybrid'))->assertCreated();

        $kinds = ImageContract::orderBy('id')->get()->map(fn (ImageContract $c) => $c->snapshot['contrepartie']['kind'])->all();
        $this->assertSame(['ugc_product', 'ugc_hybrid'], $kinds);
        $this->assertSame(2, ImageContract::count());
    }

    public function test_all_supports_collapses_to_a_single_value(): void
    {
        $this->makeChallenge($this->producerUser, 'booking_request', '123456');

        $this->postBooking($this->cashPayload(['image_terms' => $this->terms(['supports' => ['all']])]))
            ->assertCreated()
            ->assertJsonPath('data.image_contract.supports.0.value', 'all');
    }

    public function test_contact_details_are_refused_in_the_purpose_and_the_signature_name(): void
    {
        $this->makeChallenge($this->producerUser, 'booking_request', '123456');

        $this->postBooking($this->cashPayload(['image_terms' => $this->terms(['finalite' => 'Appelez-moi au 01 97 45 12 33'])]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image_terms.finalite');

        $this->postBooking($this->cashPayload(['signature' => ['name' => 'Jean 0197451233', 'code' => '123456']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('signature.name');

        $this->assertSame(0, Booking::count());
    }

    public function test_a_wrong_code_creates_no_booking_no_contract_and_no_event(): void
    {
        Event::fake([BookingCreated::class]);
        $challenge = $this->makeChallenge($this->producerUser, 'booking_request', '123456');

        $this->postBooking($this->cashPayload(['signature' => ['name' => 'Jean Producteur', 'code' => '000000']]))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SIGNATURE_CODE_INVALID');

        $this->assertSame(0, Booking::count());
        $this->assertSame(0, ImageContract::count());
        Event::assertNotDispatched(BookingCreated::class);
        // Le compteur de tentatives, lui, est bien persisté.
        $this->assertSame(1, $challenge->fresh()->attempts);
        $this->assertNull($challenge->fresh()->consumed_at);
    }

    public function test_a_wrong_code_on_ugc_creates_nothing_either(): void
    {
        Event::fake([BookingCreated::class]);
        $this->makeChallenge($this->producerUser, 'booking_request', '123456');
        $payload = $this->ugcPayload();
        $payload['signature']['code'] = '999999';

        $this->postBooking($payload)->assertUnprocessable()->assertJsonPath('error.code', 'SIGNATURE_CODE_INVALID');

        $this->assertSame(0, Booking::count());
        $this->assertSame(0, ImageContract::count());
        Event::assertNotDispatched(BookingCreated::class);
    }

    public function test_an_expired_code_creates_nothing(): void
    {
        Event::fake([BookingCreated::class]);
        $this->makeChallenge($this->producerUser, 'booking_request', '123456', ['expires_at' => now()->subMinute()]);

        $this->postBooking($this->cashPayload())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SIGNATURE_CODE_EXPIRED');

        $this->assertSame(0, Booking::count());
        Event::assertNotDispatched(BookingCreated::class);
    }

    public function test_a_consumed_code_cannot_be_used_twice(): void
    {
        Event::fake([BookingCreated::class]);
        $this->makeChallenge($this->producerUser, 'booking_request', '123456');

        $this->postBooking($this->cashPayload())->assertCreated();
        $this->postBooking($this->cashPayload())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SIGNATURE_CODE_INVALID');

        $this->assertSame(1, Booking::count());
        $this->assertSame(1, ImageContract::count());
    }

    public function test_the_sixth_attempt_is_refused_with_too_many_attempts_and_creates_nothing(): void
    {
        Event::fake([BookingCreated::class]);
        $this->makeChallenge($this->producerUser, 'booking_request', '123456');
        $wrong = $this->cashPayload(['signature' => ['name' => 'Jean Producteur', 'code' => '000000']]);

        for ($i = 0; $i < 5; $i++) {
            $this->postBooking($wrong)->assertJsonPath('error.code', 'SIGNATURE_CODE_INVALID');
        }

        $this->postBooking($this->cashPayload())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SIGNATURE_CODE_TOO_MANY_ATTEMPTS');

        $this->assertSame(0, Booking::count());
    }

    public function test_a_business_failure_after_the_code_check_does_not_burn_the_code(): void
    {
        Event::fake([BookingCreated::class]);
        // Face sans tarif : createCashBooking refuse (ValidationException) APRÈS la vérification du code.
        $this->face->update(['tarif_horaire' => 0, 'tarif_journalier' => 0]);
        $challenge = $this->makeChallenge($this->producerUser, 'booking_request', '123456');

        $this->postBooking($this->cashPayload())->assertUnprocessable();

        $this->assertSame(0, Booking::count());
        $this->assertNull($challenge->fresh()->consumed_at);
    }

    public function test_conditions_and_signature_are_required_when_enabled(): void
    {
        $payload = $this->cashPayload();
        unset($payload['image_terms'], $payload['signature']);

        $this->postBooking($payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image_terms', 'signature']);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidTermsProvider(): array
    {
        return [
            'all exclusive' => [['supports' => ['all', 'social']], 'image_terms.supports'],
            'no support' => [['supports' => []], 'image_terms.supports'],
            'unknown support' => [['supports' => ['radio']], 'image_terms.supports.0'],
            'duplicate support' => [['supports' => ['social', 'social']], 'image_terms.supports.0'],
            'unknown territory' => [['territoire' => 'mars'], 'image_terms.territoire'],
            'duration out of list' => [['duree_annees' => 2], 'image_terms.duree_annees'],
            'finalite missing' => [['finalite' => ''], 'image_terms.finalite'],
            'finalite too long' => [['finalite' => str_repeat('a', 201)], 'image_terms.finalite'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidTermsProvider')]
    public function test_invalid_conditions_are_refused(array $override, string $errorKey): void
    {
        $this->postBooking($this->cashPayload(['image_terms' => $this->terms($override)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errorKey);

        $this->assertSame(0, Booking::count());
    }

    public function test_signature_name_must_be_between_3_and_150_characters(): void
    {
        $this->postBooking($this->cashPayload(['signature' => ['name' => 'Jo', 'code' => '123456']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('signature.name');

        $this->postBooking($this->cashPayload(['signature' => ['name' => str_repeat('a', 151), 'code' => '123456']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('signature.name');
    }
}
