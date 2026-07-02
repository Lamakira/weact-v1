<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/v1/face/subscriptions/history — read-only Face billing history.
 */
class SubscriptionHistoryTest extends TestCase
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
            'email_verified_at' => now(),
        ]);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/face/subscriptions/history')
            ->assertUnauthorized();
    }

    public function test_producer_user_returns_403_with_face_envelope(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $this->actingAs($producerUser)
            ->getJson('/api/v1/face/subscriptions/history')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Accès réservé aux Faces');
    }

    public function test_returns_empty_data_when_face_has_no_subscriptions(): void
    {
        $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscriptions/history')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_returns_only_own_subscriptions_newest_first_with_billing_fields(): void
    {
        // Older expired Starter, then a more-recently-created active Pro.
        $older = FaceSubscription::factory()->starter()->expired()->create([
            'face_id' => $this->face->id,
            'provider' => 'fedapay',
            'provider_reference' => 'TX-OLD-1',
            'paid_amount' => 12000,
            'currency' => 'XOF',
            'created_at' => now()->subYears(2),
        ]);
        $newer = FaceSubscription::factory()->pro()->active()->create([
            'face_id' => $this->face->id,
            'provider' => 'fedapay',
            'provider_reference' => 'TX-NEW-2',
            'paid_amount' => 25000,
            'currency' => 'XOF',
            'created_at' => now()->subDays(3),
        ]);

        // A different Face's subscription must NOT leak in.
        $otherFace = Face::factory()->create();
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $otherFace->id]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscriptions/history')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // Newest-first ordering.
        $response->assertJsonPath('data.0.id', $newer->uuid)
            ->assertJsonPath('data.0.plan', 'pro')
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonPath('data.0.paid_amount', 25000)
            ->assertJsonPath('data.0.currency', 'XOF')
            ->assertJsonPath('data.0.provider', 'fedapay')
            ->assertJsonPath('data.0.provider_reference', 'TX-NEW-2')
            ->assertJsonPath('data.1.id', $older->uuid)
            ->assertJsonPath('data.1.plan', 'starter')
            ->assertJsonPath('data.1.status', 'expired');

        // Billing-relevant fields present; admin audit data absent.
        $response->assertJsonStructure([
            'data' => [
                [
                    'id', 'plan', 'plan_label', 'status', 'status_label',
                    'starts_at', 'expires_at', 'cancelled_at',
                    'paid_amount', 'currency', 'provider', 'provider_reference', 'created_at',
                ],
            ],
        ]);
        $this->assertArrayNotHasKey('audits', $response->json('data.0'));
    }
}
