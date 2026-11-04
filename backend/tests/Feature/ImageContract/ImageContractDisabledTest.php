<?php

declare(strict_types=1);

namespace Tests\Feature\ImageContract;

use App\Enums\BookingStatus;
use App\Http\Resources\UserResource;
use App\Jobs\GenerateImageContractPdf;
use App\Models\ImageContract;
use App\Models\SignatureChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\ImageContract\Concerns\BuildsImageContracts;
use Tests\TestCase;

class ImageContractDisabledTest extends TestCase
{
    use BuildsImageContracts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->setUpParties(enabled: false);
    }

    public function test_creation_without_conditions_is_unchanged_and_creates_no_contract(): void
    {
        $payload = $this->cashPayload();
        unset($payload['image_terms'], $payload['signature']);

        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $payload)
            ->assertCreated()
            ->assertJsonPath('data.image_contract', null);

        $this->assertSame(0, ImageContract::count());
        $this->assertSame(0, SignatureChallenge::count());
    }

    public function test_extra_conditions_sent_while_disabled_are_ignored(): void
    {
        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $this->cashPayload(['signature' => ['name' => 'x', 'code' => 'zzz']]))
            ->assertCreated();

        $this->assertSame(0, ImageContract::count());
    }

    public function test_acceptance_without_signature_is_unchanged(): void
    {
        Queue::fake([GenerateImageContractPdf::class]);
        $booking = $this->pendingCashBooking();
        // Même un contrat en base ne bloque rien tant que le drapeau est désactivé.
        $this->makeContract($booking);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::Accepted->value)
            ->assertJsonPath('data.image_contract', null);

        Queue::assertNothingPushed();
    }

    public function test_routes_answer_not_found_and_the_owner_payload_says_disabled(): void
    {
        $this->actingAs($this->producerUser)
            ->getJson('/api/v1/image-contract/template')
            ->assertNotFound();

        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/image-contract/code', ['purpose' => 'booking_request'])
            ->assertNotFound();

        $payload = UserResource::forOwner($this->producerUser)->resolve(request());
        $this->assertFalse($payload['image_contracts_enabled']);

        config(['image_contracts.enabled' => true]);
        $payload = UserResource::forOwner($this->producerUser)->resolve(request());
        $this->assertTrue($payload['image_contracts_enabled']);
    }

    public function test_listing_and_detail_run_no_image_contract_query(): void
    {
        $booking = $this->pendingCashBooking();

        DB::enableQueryLog();
        $this->actingAs($this->faceUser)->getJson('/api/v1/bookings')->assertOk();
        $this->actingAs($this->faceUser)->getJson("/api/v1/bookings/{$booking->uuid}")->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        DB::disableQueryLog();

        $this->assertStringNotContainsString('image_contracts', $queries);
    }
}
