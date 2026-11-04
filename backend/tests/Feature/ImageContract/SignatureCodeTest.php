<?php

declare(strict_types=1);

namespace Tests\Feature\ImageContract;

use App\Enums\ErrorCodes;
use App\Exceptions\SignatureCodeException;
use App\Mail\SignatureCodeMail;
use App\Models\SignatureChallenge;
use App\Services\ImageContract\SignatureChallengeService;
use App\Support\EmailMask;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\ImageContract\Concerns\BuildsImageContracts;
use Tests\TestCase;

class SignatureCodeTest extends TestCase
{
    use BuildsImageContracts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpParties();
    }

    public function test_email_mask_keeps_the_first_letter_and_the_domain(): void
    {
        $this->assertSame('a•••••@gmail.com', EmailMask::mask('aicha@gmail.com'));
    }

    public function test_template_endpoint_exposes_articles_and_allowed_values(): void
    {
        $response = $this->actingAs($this->producerUser)->getJson('/api/v1/image-contract/template');

        $response->assertOk()
            ->assertJsonPath('version', 'v1')
            ->assertJsonCount(13, 'articles')
            ->assertJsonPath('articles.0.title', 'Objet')
            ->assertJsonPath('supports.5.value', 'all')
            ->assertJsonPath('supports.0.label', 'Réseaux sociaux')
            ->assertJsonPath('territoires.1.label', 'Afrique de l\'Ouest')
            ->assertJsonPath('durees', [1, 3, 5, 10]);
    }

    public function test_producer_code_is_sent_synchronously_and_stored_hashed(): void
    {
        Mail::fake();

        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/image-contract/code', ['purpose' => 'booking_request'])
            ->assertOk()
            ->assertJsonPath('data.sent_to', 'p•••••@example.com')
            ->assertJsonPath('data.retry_after', 60)
            ->assertJsonStructure(['data' => ['sent_to', 'expires_at', 'retry_after']]);

        // Envoi synchrone : envoyé tel quel, jamais mis en file.
        Mail::assertSent(SignatureCodeMail::class, function (SignatureCodeMail $mail) {
            $challenge = SignatureChallenge::firstOrFail();

            return $mail->hasTo('producteur@example.com')
                && ! $mail instanceof ShouldQueue
                && preg_match('/^\d{6}$/', $mail->code) === 1
                && $challenge->code_hash !== $mail->code
                && Hash::check($mail->code, $challenge->code_hash)
                && $challenge->purpose === 'booking_request'
                && $challenge->expires_at->between(now()->addMinutes(9), now()->addMinutes(10)->addSeconds(5));
        });
        Mail::assertNotQueued(SignatureCodeMail::class);
    }

    public function test_a_second_code_within_60_seconds_is_refused_with_retry_after(): void
    {
        Mail::fake();

        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/image-contract/code', ['purpose' => 'booking_request'])
            ->assertOk();

        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/image-contract/code', ['purpose' => 'booking_request'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'SIGNATURE_CODE_TOO_SOON')
            ->assertJsonPath('error.retry_after', 60);

        Mail::assertSent(SignatureCodeMail::class, 1);
    }

    public function test_after_60_seconds_a_new_code_replaces_the_previous_one(): void
    {
        Mail::fake();

        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/image-contract/code', ['purpose' => 'booking_request'])
            ->assertOk();

        $this->travel(61)->seconds();

        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/image-contract/code', ['purpose' => 'booking_request'])
            ->assertOk();

        $this->assertSame(2, SignatureChallenge::count());
        $this->assertSame(1, SignatureChallenge::where('expires_at', '>', now())->count());
    }

    public function test_a_mail_failure_returns_503_and_deletes_the_challenge(): void
    {
        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')->andThrow(new \RuntimeException('smtp down'));

        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/image-contract/code', ['purpose' => 'booking_request'])
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'SIGNATURE_CODE_SEND_FAILED');

        $this->assertSame(0, SignatureChallenge::count());
    }

    public function test_a_face_cannot_request_a_booking_request_code_and_a_producer_cannot_request_accept(): void
    {
        Mail::fake();
        $booking = $this->pendingCashBooking();
        $this->makeContract($booking);

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/image-contract/code', ['purpose' => 'booking_request'])
            ->assertForbidden();

        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/image-contract/code', ['purpose' => 'booking_accept', 'booking_id' => $booking->uuid])
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_another_face_cannot_request_a_code_for_someone_elses_booking(): void
    {
        Mail::fake();
        $booking = $this->pendingCashBooking();
        $this->makeContract($booking);

        $otherFace = \App\Models\Face::factory()->create(['date_naissance' => now()->subYears(25)->toDateString()]);
        $otherUser = \App\Models\User::factory()->create([
            'userable_type' => \App\Models\Face::class,
            'userable_id' => $otherFace->id,
        ]);

        $this->actingAs($otherUser)
            ->postJson('/api/v1/image-contract/code', ['purpose' => 'booking_accept', 'booking_id' => $booking->uuid])
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_face_code_goes_to_the_face_with_a_masked_address(): void
    {
        Mail::fake();
        $booking = $this->pendingCashBooking();
        $this->makeContract($booking);

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/image-contract/code', ['purpose' => 'booking_accept', 'booking_id' => $booking->uuid])
            ->assertOk()
            ->assertJsonPath('data.sent_to', 'a•••••@gmail.com');

        Mail::assertSent(SignatureCodeMail::class, fn (SignatureCodeMail $mail) => $mail->hasTo('aicha@gmail.com') && ! $mail->forGuardian);
        $this->assertSame('booking_accept:'.$booking->uuid, SignatureChallenge::firstOrFail()->purpose);
    }

    public function test_no_code_for_a_booking_without_contract_or_already_signed_by_the_face(): void
    {
        Mail::fake();
        $withoutContract = $this->pendingCashBooking();
        $signed = $this->pendingCashBooking();
        $this->makeSignedContract($signed);

        foreach ([$withoutContract, $signed] as $booking) {
            $this->actingAs($this->faceUser)
                ->postJson('/api/v1/image-contract/code', ['purpose' => 'booking_accept', 'booking_id' => $booking->uuid])
                ->assertUnprocessable()
                ->assertJsonPath('error.code', 'INVALID_STATUS');
        }

        Mail::assertNothingSent();
    }

    public function test_a_face_without_birth_date_gets_birth_date_required(): void
    {
        Mail::fake();
        $this->face->update(['date_naissance' => null]);
        $booking = $this->pendingCashBooking();
        $this->makeContract($booking);

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/image-contract/code', ['purpose' => 'booking_accept', 'booking_id' => $booking->uuid])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', ErrorCodes::BirthDateRequired->value);

        Mail::assertNothingSent();
    }

    public function test_a_minor_face_must_name_a_guardian_and_the_code_goes_to_the_guardian(): void
    {
        Mail::fake();
        $this->face->update(['date_naissance' => now()->subYears(16)->toDateString()]);
        $booking = $this->pendingCashBooking();
        $this->makeContract($booking);
        $payload = ['purpose' => 'booking_accept', 'booking_id' => $booking->uuid];

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/image-contract/code', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('guardian_name');

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/image-contract/code', $payload + [
                'guardian_name' => 'Fatou Bello',
                'guardian_relation' => 'mere',
                'guardian_email' => 'fatou@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('data.sent_to', 'f•••••@example.com');

        Mail::assertSent(SignatureCodeMail::class, fn (SignatureCodeMail $mail) => $mail->hasTo('fatou@example.com') && $mail->forGuardian);
        Mail::assertNotSent(SignatureCodeMail::class, fn (SignatureCodeMail $mail) => $mail->hasTo('aicha@gmail.com'));

        $challenge = SignatureChallenge::firstOrFail();
        $this->assertSame('Fatou Bello', $challenge->guardian_name);
        $this->assertSame('mere', $challenge->guardian_relation);
        $this->assertSame('fatou@example.com', $challenge->email);
    }

    public function test_verify_accepts_a_good_code_once_then_refuses_the_consumed_one(): void
    {
        $service = app(SignatureChallengeService::class);
        $this->makeChallenge($this->producerUser, 'booking_request', '424242');

        $challenge = $service->verify($this->producerUser, 'booking_request', '424 242');
        $this->assertNotNull($challenge->fresh()->consumed_at);

        try {
            $service->verify($this->producerUser, 'booking_request', '424242');
            $this->fail('Un code consommé doit être refusé.');
        } catch (SignatureCodeException $e) {
            $this->assertSame(ErrorCodes::SignatureCodeInvalid, $e->errorCode);
        }
    }

    public function test_verify_refuses_an_expired_code(): void
    {
        $this->makeChallenge($this->producerUser, 'booking_request', '424242', ['expires_at' => now()->subSecond()]);

        try {
            app(SignatureChallengeService::class)->verify($this->producerUser, 'booking_request', '424242');
            $this->fail('Un code expiré doit être refusé.');
        } catch (SignatureCodeException $e) {
            $this->assertSame(ErrorCodes::SignatureCodeExpired, $e->errorCode);
            $this->assertSame(422, $e->status);
        }
    }

    public function test_the_sixth_attempt_is_refused_even_with_the_right_code(): void
    {
        $service = app(SignatureChallengeService::class);
        $challenge = $this->makeChallenge($this->producerUser, 'booking_request', '424242');

        for ($i = 1; $i <= 5; $i++) {
            $result = $service->attempt($this->producerUser, 'booking_request', '000000');
            $this->assertInstanceOf(SignatureCodeException::class, $result);
            $this->assertSame(ErrorCodes::SignatureCodeInvalid, $result->errorCode);
            $this->assertSame($i, $challenge->fresh()->attempts);
        }

        $result = $service->attempt($this->producerUser, 'booking_request', '424242');
        $this->assertInstanceOf(SignatureCodeException::class, $result);
        $this->assertSame(ErrorCodes::SignatureCodeTooManyAttempts, $result->errorCode);
        $this->assertNull($challenge->fresh()->consumed_at);
    }

    public function test_a_code_is_bound_to_its_user_and_purpose(): void
    {
        $this->makeChallenge($this->producerUser, 'booking_request', '424242');

        $this->expectException(SignatureCodeException::class);
        app(SignatureChallengeService::class)->verify($this->faceUser, 'booking_request', '424242');
    }
}
