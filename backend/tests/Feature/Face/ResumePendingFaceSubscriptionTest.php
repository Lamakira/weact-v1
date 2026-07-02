<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Enums\FaceSubscriptionStatus;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Producer;
use App\Models\User;
use App\Services\FedapayService;
use FedaPay\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ResumePendingFaceSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private Face $face;

    private User $faceUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    public function test_face_can_resume_their_pending_payment_when_fedapay_status_is_pending(): void
    {
        $pending = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '123',
            'currency' => 'XOF',
            'metadata' => [
                'quoted_amount' => 25000,
                'quoted_currency' => 'XOF',
                'idempotency_key' => 'idem-original',
                'initiated_at' => now()->toIso8601String(),
            ],
        ]);

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('retrieveTransaction')
                ->once()
                ->with(123)
                ->andReturn(new Transaction([
                    'id' => 123,
                    'status' => 'pending',
                    'reference' => null,
                    'amount' => 25000,
                    'currency' => ['iso' => 'XOF'],
                ]));

            $mock->shouldReceive('regenerateTokenFromTransaction')
                ->once()
                ->andReturn([
                    'checkout_url' => 'https://checkout.fedapay.test/sess_fresh_xyz',
                    'fedapay_status' => 'pending',
                ]);
        });

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/resume-payment');

        $response->assertOk()
            ->assertJsonPath('data.subscription_id', $pending->uuid)
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.checkout_url', 'https://checkout.fedapay.test/sess_fresh_xyz')
            ->assertJsonPath('data.amount', 25000)
            ->assertJsonPath('data.currency', 'XOF')
            ->assertJsonPath('message', 'Reprise du paiement…');

        $fresh = $pending->fresh();
        $this->assertSame(FaceSubscriptionStatus::PendingPayment, $fresh->status);
        $this->assertSame('idem-original', $fresh->metadata['idempotency_key']);
    }

    public function test_resume_increments_metadata_resume_count_and_sets_last_resumed_at(): void
    {
        $pending = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '123',
            'currency' => 'XOF',
            'metadata' => [
                'quoted_amount' => 25000,
                'quoted_currency' => 'XOF',
            ],
        ]);

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('retrieveTransaction')
                ->once()
                ->with(123)
                ->andReturn(new Transaction([
                    'id' => 123,
                    'status' => 'pending',
                ]));

            $mock->shouldReceive('regenerateTokenFromTransaction')
                ->once()
                ->andReturn([
                    'checkout_url' => 'https://checkout.fedapay.test/sess1',
                    'fedapay_status' => 'pending',
                ]);
        });

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/resume-payment')
            ->assertOk();

        $fresh = $pending->fresh();
        $this->assertSame(1, $fresh->metadata['resume_count']);
        $this->assertIsString($fresh->metadata['last_resumed_at']);
        // ISO 8601 sanity check (e.g., 2026-05-28T14:00:00+00:00).
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+\-]\d{2}:\d{2}$/',
            $fresh->metadata['last_resumed_at'],
        );
    }

    public function test_resume_returns_410_resume_not_available_when_fedapay_status_is_declined(): void
    {
        $pending = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '123',
            'currency' => 'XOF',
            'metadata' => ['quoted_amount' => 25000, 'quoted_currency' => 'XOF'],
        ]);

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('retrieveTransaction')
                ->once()
                ->with(123)
                ->andReturn(new Transaction([
                    'id' => 123,
                    'status' => 'declined',
                    'reference' => 'fp_ref_declined',
                ]));

            $mock->shouldNotReceive('regenerateTokenFromTransaction');
        });

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/resume-payment');

        $response->assertStatus(410)
            ->assertJsonPath('error.code', 'RESUME_NOT_AVAILABLE')
            ->assertJsonPath(
                'error.message',
                'Ce paiement ne peut plus être repris. Veuillez en initier un nouveau depuis la page Tarifs.'
            );

        $this->assertSame(FaceSubscriptionStatus::Failed, $pending->fresh()->status);
    }

    public function test_resume_returns_410_resume_not_available_when_fedapay_status_is_canceled(): void
    {
        $pending = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '456',
            'currency' => 'XOF',
            'metadata' => ['quoted_amount' => 25000, 'quoted_currency' => 'XOF'],
        ]);

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('retrieveTransaction')
                ->once()
                ->with(456)
                ->andReturn(new Transaction([
                    'id' => 456,
                    'status' => 'canceled',
                    'reference' => 'fp_ref_canceled',
                ]));
        });

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/resume-payment');

        $response->assertStatus(410)
            ->assertJsonPath('error.code', 'RESUME_NOT_AVAILABLE');

        $this->assertSame(FaceSubscriptionStatus::Failed, $pending->fresh()->status);
    }

    public function test_resume_returns_410_resume_not_available_when_fedapay_status_is_expired(): void
    {
        $pending = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '789',
            'currency' => 'XOF',
            'metadata' => ['quoted_amount' => 25000, 'quoted_currency' => 'XOF'],
        ]);

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('retrieveTransaction')
                ->once()
                ->with(789)
                ->andReturn(new Transaction([
                    'id' => 789,
                    'status' => 'expired',
                    'reference' => 'fp_ref_expired',
                ]));
        });

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/resume-payment');

        $response->assertStatus(410)
            ->assertJsonPath('error.code', 'RESUME_NOT_AVAILABLE');

        $this->assertSame(FaceSubscriptionStatus::Failed, $pending->fresh()->status);
    }

    public function test_resume_returns_200_with_status_active_when_fedapay_status_is_approved(): void
    {
        $pending = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '321',
            'currency' => 'XOF',
            'metadata' => [
                'quoted_amount' => 25000,
                'quoted_currency' => 'XOF',
            ],
        ]);

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('retrieveTransaction')
                ->once()
                ->with(321)
                ->andReturn(new Transaction([
                    'id' => 321,
                    'status' => 'approved',
                    'reference' => 'fedapay_ref_abc',
                    'amount' => 25000,
                    'currency' => ['iso' => 'XOF'],
                ]));

            $mock->shouldNotReceive('regenerateTokenFromTransaction');
        });

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/resume-payment');

        $response->assertOk()
            ->assertJsonPath('data.subscription_id', $pending->uuid)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.checkout_url', null)
            ->assertJsonPath('data.amount', 25000)
            ->assertJsonPath('data.currency', 'XOF')
            ->assertJsonPath('message', 'Le paiement a déjà été confirmé.');

        $this->assertSame(FaceSubscriptionStatus::Active, $pending->fresh()->status);
    }

    public function test_resume_returns_404_no_pending_payment_when_face_has_no_pending_row(): void
    {
        // No pending row created for $this->face.

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldNotReceive('retrieveTransaction');
        });

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/resume-payment');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'NO_PENDING_PAYMENT')
            ->assertJsonPath('error.message', 'Aucun paiement en attente.');
    }

    public function test_resume_returns_409_cannot_resume_without_provider_reference_when_provider_reference_is_null(): void
    {
        $pending = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => null,
            'metadata' => ['quoted_amount' => 25000, 'quoted_currency' => 'XOF'],
        ]);

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldNotReceive('retrieveTransaction');
        });

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/resume-payment');

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'CANNOT_RESUME_WITHOUT_PROVIDER_REFERENCE')
            ->assertJsonPath(
                'error.message',
                'Ce paiement ne peut pas être repris automatiquement. Veuillez en initier un nouveau depuis la page Tarifs.'
            );

        // Row unchanged
        $this->assertSame(FaceSubscriptionStatus::PendingPayment, $pending->fresh()->status);
    }

    public function test_resume_returns_403_forbidden_when_user_is_not_a_face(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $response = $this->actingAs($producerUser)
            ->postJson('/api/v1/face/subscription/resume-payment');

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Accès réservé aux Faces');
    }

    public function test_resume_returns_401_when_unauthenticated(): void
    {
        $response = $this->postJson('/api/v1/face/subscription/resume-payment');

        $response->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED')
            ->assertJsonPath('error.message', 'Non authentifié.');
    }

    public function test_resume_returns_502_payment_initiation_failed_when_fedapay_throws(): void
    {
        $pending = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '999',
            'currency' => 'XOF',
            'metadata' => ['quoted_amount' => 25000, 'quoted_currency' => 'XOF'],
        ]);

        Log::spy();

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('retrieveTransaction')
                ->once()
                ->with(999)
                ->andThrow(new \Exception('Fedapay network error'));
        });

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/resume-payment');

        $response->assertStatus(502)
            ->assertJsonPath('error.code', 'PAYMENT_INITIATION_FAILED')
            ->assertJsonPath(
                'error.message',
                'Le paiement ne peut pas être repris pour le moment. Veuillez réessayer.'
            );

        // Pending row unchanged (still PendingPayment, still has provider_reference)
        $fresh = $pending->fresh();
        $this->assertSame(FaceSubscriptionStatus::PendingPayment, $fresh->status);
        $this->assertSame('999', $fresh->provider_reference);

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context) use ($pending): bool {
                return $message === 'Face subscription resume: phase failed'
                    && ($context['face_subscription_id'] ?? null) === $pending->id
                    && ($context['phase'] ?? null) === 'retrieve_transaction';
            })
            ->once();
    }

    public function test_resume_returns_502_when_fedapay_returns_unknown_status(): void
    {
        $pending = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '222',
            'currency' => 'XOF',
            'metadata' => ['quoted_amount' => 25000, 'quoted_currency' => 'XOF'],
        ]);

        Log::spy();

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('retrieveTransaction')
                ->once()
                ->with(222)
                ->andReturn(new Transaction([
                    'id' => 222,
                    'status' => 'transferred',
                ]));

            $mock->shouldNotReceive('regenerateTokenFromTransaction');
        });

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/resume-payment');

        $response->assertStatus(502)
            ->assertJsonPath('error.code', 'PAYMENT_INITIATION_FAILED');

        // Row unchanged
        $this->assertSame(FaceSubscriptionStatus::PendingPayment, $pending->fresh()->status);

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context) use ($pending): bool {
                return $message === 'Face subscription resume: unknown Fedapay status'
                    && ($context['face_subscription_id'] ?? null) === $pending->id
                    && ($context['fedapay_status'] ?? null) === 'transferred';
            })
            ->once();
    }

    public function test_resume_respects_rate_limit_throttle_30_per_minute(): void
    {
        $pending = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider_reference' => '111',
            'currency' => 'XOF',
            'metadata' => ['quoted_amount' => 25000, 'quoted_currency' => 'XOF'],
        ]);

        // Mock to keep the pending branch idempotent across 30 successful calls.
        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('retrieveTransaction')
                ->andReturn(new Transaction([
                    'id' => 111,
                    'status' => 'pending',
                ]));
            $mock->shouldReceive('regenerateTokenFromTransaction')
                ->andReturn([
                    'checkout_url' => 'https://checkout.fedapay.test/sess_throttle',
                    'fedapay_status' => 'pending',
                ]);
        });

        // 30 requests succeed
        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($this->faceUser)
                ->postJson('/api/v1/face/subscription/resume-payment')
                ->assertOk();
        }

        // 31st is throttled
        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/subscription/resume-payment')
            ->assertStatus(429);

        // Pending row preserved (still pending — resume does not flip it on the happy path)
        $this->assertSame(FaceSubscriptionStatus::PendingPayment, $pending->fresh()->status);
    }

    public function test_resume_does_not_require_email_verification(): void
    {
        // Face user WITHOUT email_verified_at but WITH a pending subscription.
        $unverifiedFace = Face::factory()->create();
        $unverifiedFaceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $unverifiedFace->id,
            'email_verified_at' => null,
        ]);

        $pending = FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $unverifiedFace->id,
            'provider_reference' => '555',
            'currency' => 'XOF',
            'metadata' => ['quoted_amount' => 25000, 'quoted_currency' => 'XOF'],
        ]);

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('retrieveTransaction')
                ->once()
                ->with(555)
                ->andReturn(new Transaction([
                    'id' => 555,
                    'status' => 'pending',
                ]));

            $mock->shouldReceive('regenerateTokenFromTransaction')
                ->once()
                ->andReturn([
                    'checkout_url' => 'https://checkout.fedapay.test/sess_unverified',
                    'fedapay_status' => 'pending',
                ]);
        });

        $response = $this->actingAs($unverifiedFaceUser)
            ->postJson('/api/v1/face/subscription/resume-payment');

        $response->assertOk()
            ->assertJsonPath('data.subscription_id', $pending->uuid)
            ->assertJsonPath('data.status', 'pending_payment');
    }
}
