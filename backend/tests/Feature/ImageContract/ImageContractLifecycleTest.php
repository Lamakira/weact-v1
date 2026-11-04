<?php

declare(strict_types=1);

namespace Tests\Feature\ImageContract;

use App\Enums\BookingStatus;
use App\Enums\UgcTunnelStatus;
use App\Events\BookingCancelled;
use App\Events\BookingCompleted;
use App\Events\BookingExpired;
use App\Events\BookingRefused;
use App\Events\ProductReceived;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\ImageContract;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\ImageContract\Concerns\BuildsImageContracts;
use Tests\TestCase;

class ImageContractLifecycleTest extends TestCase
{
    use BuildsImageContracts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->setUpParties();
    }

    private function ugcBooking(string $compensation): Booking
    {
        return Booking::factory()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => BookingStatus::Accepted,
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
            'commission_paid_at' => now()->subDay(),
        ]);
    }

    private function receivedShipment(Booking|Candidature $owner): \App\Models\Shipment
    {
        return $owner->shipment()->create([
            'transporteur' => 'Gozem',
            'numero_suivi' => 'GZM-1',
            'tunnel_status' => UgcTunnelStatus::Received,
            'shipped_at' => now()->subDay(),
            'recu_le' => now(),
            'destinataire_nom' => 'Aïcha Bello',
        ]);
    }

    public function test_completion_makes_a_signed_contract_effective_once(): void
    {
        $booking = $this->pendingCashBooking();
        $contract = $this->makeSignedContract($booking);

        BookingCompleted::dispatch($booking);
        $effectiveAt = $contract->fresh()->effective_at;
        $this->assertNotNull($effectiveAt);
        $this->assertSame('effective', $contract->fresh()->status());

        $this->travel(2)->hours();
        BookingCompleted::dispatch($booking);
        $this->assertTrue($effectiveAt->equalTo($contract->fresh()->effective_at));
    }

    public function test_completion_leaves_an_unsigned_contract_and_a_booking_without_contract_alone(): void
    {
        $unsigned = $this->pendingCashBooking();
        $contract = $this->makeContract($unsigned);
        $without = $this->pendingCashBooking();

        BookingCompleted::dispatch($unsigned);
        BookingCompleted::dispatch($without);

        $this->assertNull($contract->fresh()->effective_at);
        $this->assertSame(1, ImageContract::count());
    }

    public function test_product_received_makes_a_product_only_ugc_contract_effective(): void
    {
        $booking = $this->ugcBooking('product');
        $contract = $this->makeSignedContract($booking);

        ProductReceived::dispatch($this->receivedShipment($booking));

        $this->assertNotNull($contract->fresh()->effective_at);
    }

    public function test_product_received_does_not_make_a_hybrid_contract_effective(): void
    {
        $booking = $this->ugcBooking('hybrid');
        $contract = $this->makeSignedContract($booking);

        ProductReceived::dispatch($this->receivedShipment($booking));
        $this->assertNull($contract->fresh()->effective_at);

        // La Face est payée en argent : prise d'effet à la complétion.
        BookingCompleted::dispatch($booking);
        $this->assertNotNull($contract->fresh()->effective_at);
    }

    public function test_product_received_on_a_candidature_shipment_is_ignored(): void
    {
        $booking = $this->ugcBooking('product');
        $contract = $this->makeSignedContract($booking);
        $candidature = Candidature::factory()->create();

        ProductReceived::dispatch($this->receivedShipment($candidature));

        $this->assertNull($contract->fresh()->effective_at);
    }

    public function test_a_refusal_voids_the_contract(): void
    {
        $booking = $this->pendingCashBooking();
        $contract = $this->makeContract($booking);

        app(BookingService::class)->refuse($booking, 'Indisponible');

        $this->assertNotNull($contract->fresh()->voided_at);
        $this->assertSame('void', $contract->fresh()->status());
    }

    public function test_a_producer_cancellation_voids_the_contract(): void
    {
        $booking = $this->pendingCashBooking();
        $contract = $this->makeSignedContract($booking);

        app(BookingService::class)->cancel($booking, 'other', 'Changement de plan');

        $this->assertSame(BookingStatus::CancelledByProducer, $booking->fresh()->status);
        $this->assertSame('void', $contract->fresh()->status());
    }

    public function test_an_expiration_voids_the_contract(): void
    {
        $booking = Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);
        $contract = $this->makeSignedContract($booking);

        app(BookingService::class)->expire($booking);

        $this->assertSame(BookingStatus::Expired, $booking->fresh()->status);
        $this->assertSame('void', $contract->fresh()->status());
    }

    public function test_the_end_events_void_an_unsigned_contract_too(): void
    {
        foreach ([fn (Booking $b) => new BookingRefused($b), fn (Booking $b) => new BookingCancelled($b), fn (Booking $b) => new BookingExpired($b)] as $make) {
            $booking = $this->pendingCashBooking();
            $contract = $this->makeContract($booking);

            event($make($booking));

            $this->assertNotNull($contract->fresh()->voided_at);
        }
    }

    public function test_an_effective_contract_never_goes_back_to_void(): void
    {
        $booking = $this->pendingCashBooking();
        $contract = $this->makeSignedContract($booking, ['effective_at' => now()->subDay()]);

        event(new BookingCancelled($booking));
        event(new BookingRefused($booking));
        event(new BookingExpired($booking));

        $this->assertNull($contract->fresh()->voided_at);
        $this->assertSame('effective', $contract->fresh()->status());
    }

    public function test_voiding_is_idempotent(): void
    {
        $booking = $this->pendingCashBooking();
        $contract = $this->makeContract($booking);

        event(new BookingRefused($booking));
        $voidedAt = $contract->fresh()->voided_at;
        $this->travel(1)->hour();
        event(new BookingExpired($booking));

        $this->assertTrue($voidedAt->equalTo($contract->fresh()->voided_at));
    }
}
