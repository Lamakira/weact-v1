<?php

declare(strict_types=1);

namespace Tests\Feature\ImageContract;

use App\Enums\BookingStatus;
use App\Events\BookingAccepted;
use App\Jobs\GenerateImageContractPdf;
use App\Models\Booking;
use App\Models\ImageContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\ImageContract\Concerns\BuildsImageContracts;
use Tests\TestCase;

class AcceptBookingWithImageContractTest extends TestCase
{
    use BuildsImageContracts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Queue::fake([GenerateImageContractPdf::class]);
        $this->setUpParties();
    }

    private function accept(Booking $booking, ?array $signature = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->faceUser)
            ->withHeaders(['User-Agent' => 'FaceBrowser/2.0'])
            ->postJson("/api/v1/bookings/{$booking->uuid}/accept", $signature === null ? [] : ['signature' => $signature]);
    }

    private function ugcCommissionPaidBooking(string $compensation = 'product'): Booking
    {
        return Booking::factory()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => BookingStatus::CommissionPaid,
            'date_debut' => null,
            'date_fin' => null,
            'duree_heures' => null,
            'type_contenu' => 'UGC',
            'lieu' => null,
            'tarif_base' => 0,
            'type_compensation' => $compensation,
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'commission_ugc' => 2500,
            'montant_face_recoit' => 0,
            'montant_total_producteur' => 2500,
            'commission_paid_at' => now()->subHour(),
        ]);
    }

    public function test_accepting_with_a_valid_signature_signs_the_contract_and_dispatches_the_pdf_job(): void
    {
        $booking = $this->pendingCashBooking();
        $contract = $this->makeContract($booking);
        $this->makeChallenge($this->faceUser, $this->acceptPurpose($booking), '654321');

        $this->accept($booking, ['name' => 'Aïcha Bello', 'code' => '654321'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.image_contract.status', 'signed')
            ->assertJsonPath('data.image_contract.face_signed_name', 'Aïcha Bello')
            ->assertJsonPath('data.image_contract.requires_guardian', false);

        $contract->refresh();
        $this->assertSame(BookingStatus::Accepted, $booking->fresh()->status);
        $this->assertSame('Aïcha Bello', $contract->face_signed_name);
        $this->assertNotNull($contract->face_signed_at);
        $this->assertSame('a•••••@gmail.com', $contract->face_signed_email_masked);
        $this->assertSame('FaceBrowser/2.0', $contract->face_user_agent);
        $this->assertNull($contract->guardian_name);

        Queue::assertPushed(GenerateImageContractPdf::class, fn (GenerateImageContractPdf $job) => $job->contractId === $contract->id && $job->afterCommit === true);
    }

    public function test_accepting_without_signature_is_refused_and_the_status_is_unchanged(): void
    {
        Event::fake([BookingAccepted::class]);
        $booking = $this->pendingCashBooking();
        $this->makeContract($booking);

        $this->accept($booking)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('signature');

        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
        Event::assertNotDispatched(BookingAccepted::class);
        Queue::assertNothingPushed();
    }

    public function test_the_service_itself_refuses_an_unsigned_acceptance(): void
    {
        // Garde de dernier recours : un appelant qui contourne le FormRequest ne peut pas accepter.
        $booking = $this->pendingCashBooking();
        $this->makeContract($booking);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        try {
            app(\App\Services\BookingService::class)->accept($booking);
        } finally {
            $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
        }
    }

    public function test_a_wrong_code_leaves_the_booking_unchanged_and_counts_the_attempt(): void
    {
        Event::fake([BookingAccepted::class]);
        $booking = $this->pendingCashBooking();
        $contract = $this->makeContract($booking);
        $challenge = $this->makeChallenge($this->faceUser, $this->acceptPurpose($booking), '654321');

        $this->accept($booking, ['name' => 'Aïcha Bello', 'code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SIGNATURE_CODE_INVALID');

        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
        $this->assertNull($contract->fresh()->face_signed_at);
        $this->assertNull($booking->fresh()->accepted_at);
        $this->assertSame(1, $challenge->fresh()->attempts);
        Event::assertNotDispatched(BookingAccepted::class);
        Queue::assertNothingPushed();
    }

    public function test_an_expired_or_consumed_code_cannot_accept(): void
    {
        $booking = $this->pendingCashBooking();
        $this->makeContract($booking);
        $this->makeChallenge($this->faceUser, $this->acceptPurpose($booking), '654321', ['expires_at' => now()->subSecond()]);

        $this->accept($booking, ['name' => 'Aïcha Bello', 'code' => '654321'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SIGNATURE_CODE_EXPIRED');

        $this->makeChallenge($this->faceUser, $this->acceptPurpose($booking), '111111', ['consumed_at' => now()]);

        $this->accept($booking, ['name' => 'Aïcha Bello', 'code' => '111111'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SIGNATURE_CODE_INVALID');

        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
    }

    public function test_the_code_of_another_booking_does_not_sign(): void
    {
        $booking = $this->pendingCashBooking();
        $other = $this->pendingCashBooking();
        $this->makeContract($booking);
        $this->makeContract($other);
        $this->makeChallenge($this->faceUser, $this->acceptPurpose($other), '654321');

        $this->accept($booking, ['name' => 'Aïcha Bello', 'code' => '654321'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SIGNATURE_CODE_INVALID');

        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
    }

    public function test_a_booking_without_contract_is_accepted_as_before(): void
    {
        $booking = $this->pendingCashBooking();

        $this->accept($booking)
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.image_contract', null);

        $this->assertSame(0, ImageContract::count());
        Queue::assertNothingPushed();
    }

    public function test_a_contract_already_signed_by_the_face_does_not_ask_again(): void
    {
        $booking = $this->pendingCashBooking();
        $this->makeSignedContract($booking);

        $this->accept($booking)->assertOk()->assertJsonPath('data.status', 'accepted');
        Queue::assertNothingPushed();
    }

    public function test_the_ugc_path_signs_on_acceptance_after_the_commission_is_paid(): void
    {
        // Une Face n'accepte un deal UGC qu'abonnée Pro+ (garde FR5 du contrôleur).
        \App\Models\FaceSubscription::factory()->pro()->active()->create(['face_id' => $this->face->id]);
        $booking = $this->ugcCommissionPaidBooking();
        $contract = $this->makeContract($booking, ['snapshot' => ['contrepartie' => ['kind' => 'ugc_product']]]);

        // Sans signature : refus, statut inchangé.
        $this->accept($booking)->assertUnprocessable()->assertJsonValidationErrors('signature');
        $this->assertSame(BookingStatus::CommissionPaid, $booking->fresh()->status);

        $this->makeChallenge($this->faceUser, $this->acceptPurpose($booking), '654321');
        $this->accept($booking, ['name' => 'Aïcha Bello', 'code' => '654321'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.image_contract.status', 'signed');

        $this->assertNotNull($contract->fresh()->face_signed_at);
        Queue::assertPushed(GenerateImageContractPdf::class, 1);
    }

    public function test_a_minor_face_signs_with_the_guardian_taken_from_the_challenge(): void
    {
        $this->face->update(['date_naissance' => now()->subYears(16)->toDateString()]);
        $booking = $this->pendingCashBooking();
        $contract = $this->makeContract($booking);
        $this->makeChallenge($this->faceUser, $this->acceptPurpose($booking), '654321', [
            'email' => 'fatou@example.com',
            'guardian_name' => 'Fatou Bello',
            'guardian_relation' => 'mere',
        ]);

        $this->accept($booking, ['name' => 'Aïcha Bello', 'code' => '654321'])->assertOk();

        $contract->refresh();
        $this->assertSame('Fatou Bello', $contract->guardian_name);
        $this->assertSame('mere', $contract->guardian_relation);
        $this->assertSame('f•••••@example.com', $contract->face_signed_email_masked);
    }

    public function test_a_minor_face_cannot_sign_with_a_code_that_did_not_go_to_a_guardian(): void
    {
        $this->face->update(['date_naissance' => now()->subYears(16)->toDateString()]);
        $booking = $this->pendingCashBooking();
        $this->makeContract($booking);
        $challenge = $this->makeChallenge($this->faceUser, $this->acceptPurpose($booking), '654321');

        $this->accept($booking, ['name' => 'Aïcha Bello', 'code' => '654321'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SIGNATURE_CODE_INVALID');

        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
        $this->assertNull($challenge->fresh()->consumed_at);
    }

    public function test_the_detail_tells_a_minor_face_a_guardian_is_required(): void
    {
        $this->face->update(['date_naissance' => now()->subYears(16)->toDateString()]);
        $booking = $this->pendingCashBooking();
        $this->makeContract($booking);

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertOk()
            ->assertJsonPath('data.image_contract.status', 'awaiting_face')
            ->assertJsonPath('data.image_contract.requires_guardian', true);

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertOk()
            ->assertJsonMissingPath('data.image_contract.requires_guardian');
    }

    public function test_a_third_party_hitting_accept_still_gets_forbidden_not_a_validation_error(): void
    {
        $booking = $this->pendingCashBooking();
        $this->makeContract($booking);

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/accept")
            ->assertForbidden();
    }

    public function test_a_consumed_code_stays_consumed_under_a_double_submit(): void
    {
        $booking = $this->pendingCashBooking();
        $this->makeContract($booking);
        $this->makeChallenge($this->faceUser, $this->acceptPurpose($booking), '654321');
        $signature = ['name' => 'Aïcha Bello', 'code' => '654321'];

        $this->accept($booking, $signature)->assertOk();
        // Deuxième clic : le booking n'est plus acceptable, le code reste consommé.
        $this->accept($booking, $signature)->assertStatus(403);

        $this->assertSame(1, \App\Models\SignatureChallenge::whereNotNull('consumed_at')->count());
        Queue::assertPushed(GenerateImageContractPdf::class, 1);
    }
}
