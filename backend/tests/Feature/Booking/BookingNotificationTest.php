<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Events\BookingAccepted;
use App\Events\BookingCancelled;
use App\Events\BookingCompleted;
use App\Events\BookingCreated;
use App\Events\BookingPaid;
use App\Events\BookingRefused;
use App\Listeners\Booking\NotifyFaceOnBookingReceived;
use App\Listeners\Booking\NotifyPartiesOnBookingCompleted;
use App\Listeners\Booking\NotifyPartyOnBookingCancelled;
use App\Listeners\Booking\NotifyProducerOnBookingAccepted;
use App\Listeners\Booking\NotifyPartiesOnBookingPaid;
use App\Listeners\Booking\NotifyProducerOnBookingRefused;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    private User $faceUser;

    private Face $face;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
        ]);
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);

        $this->face = Face::factory()->create([
            'prenom' => 'Awa',
            'nom' => 'Diallo',
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);

        $this->booking = Booking::factory()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => BookingStatus::Pending,
            'type_contenu' => 'publicité',
            'montant_face_recoit' => 50000,
        ]);
    }

    public function test_booking_created_creates_notification_for_face(): void
    {
        $listener = new NotifyFaceOnBookingReceived;
        $listener->handle(new BookingCreated($this->booking));

        $notification = Notification::where('user_id', $this->faceUser->id)
            ->where('type', 'booking_received')
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals($this->booking->id, $notification->data['booking_id']);
        $this->assertStringContainsString('publicité', $notification->data['message']);
        $this->assertEquals("/face/bookings/{$this->booking->id}", $notification->data['url']);
    }

    public function test_booking_accepted_creates_notification_for_producer(): void
    {
        $this->booking->update(['status' => BookingStatus::Accepted]);

        $listener = new NotifyProducerOnBookingAccepted;
        $listener->handle(new BookingAccepted($this->booking));

        $notification = Notification::where('user_id', $this->producerUser->id)
            ->where('type', 'booking_accepted')
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals($this->booking->id, $notification->data['booking_id']);
        $this->assertStringContainsString('accepté', $notification->data['message']);
        $this->assertEquals("/producer/bookings/{$this->booking->id}", $notification->data['url']);
    }

    public function test_booking_refused_creates_notification_for_producer(): void
    {
        $this->booking->update(['status' => BookingStatus::Refused]);

        $listener = new NotifyProducerOnBookingRefused;
        $listener->handle(new BookingRefused($this->booking));

        $notification = Notification::where('user_id', $this->producerUser->id)
            ->where('type', 'booking_refused')
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals($this->booking->id, $notification->data['booking_id']);
        $this->assertStringContainsString('refusé', $notification->data['message']);
    }

    public function test_booking_paid_creates_notification_for_producer(): void
    {
        $this->booking->update(['status' => BookingStatus::Paid]);

        $listener = new NotifyPartiesOnBookingPaid;
        $listener->handle(new BookingPaid($this->booking));

        $notification = Notification::where('user_id', $this->producerUser->id)
            ->where('type', 'booking_paid')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('Paiement confirmé', $notification->data['message']);
        $this->assertStringContainsString('chat', $notification->data['message']);
        $this->assertEquals("/producer/bookings/{$this->booking->id}", $notification->data['url']);
    }

    public function test_booking_paid_creates_notification_for_face(): void
    {
        $this->booking->update(['status' => BookingStatus::Paid]);

        $listener = new NotifyPartiesOnBookingPaid;
        $listener->handle(new BookingPaid($this->booking));

        $notification = Notification::where('user_id', $this->faceUser->id)
            ->where('type', 'booking_paid')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('paiement', $notification->data['message']);
        $this->assertStringContainsString('chat', $notification->data['message']);
        $this->assertEquals("/face/bookings/{$this->booking->id}", $notification->data['url']);
    }

    public function test_partial_confirmation_creates_notification_for_other_party(): void
    {
        // Face confirms first → Producer gets notified.
        // Booking must be Paid before confirmation is allowed.
        $this->booking->update([
            'status' => BookingStatus::Paid,
            'date_debut' => now()->subDay(),
        ]);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$this->booking->id}/confirm")
            ->assertSuccessful();

        $notification = Notification::where('user_id', $this->producerUser->id)
            ->where('type', 'booking_confirmation_pending')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('Face a confirmé', $notification->data['message']);
        $this->assertStringContainsString('votre tour', $notification->data['message']);
        $this->assertEquals("/producer/bookings/{$this->booking->id}", $notification->data['url']);
    }

    public function test_booking_completed_creates_wallet_notification_for_face(): void
    {
        $this->booking->update(['status' => BookingStatus::Completed]);

        $listener = new NotifyPartiesOnBookingCompleted;
        $listener->handle(new BookingCompleted($this->booking));

        $notification = Notification::where('user_id', $this->faceUser->id)
            ->where('type', 'booking_wallet_credited')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('XOF', $notification->data['message']);
        $this->assertStringContainsString('wallet', $notification->data['message']);
        $this->assertEquals($this->booking->id, $notification->data['booking_id']);
    }

    public function test_booking_completed_creates_completion_notification_for_producer(): void
    {
        $this->booking->update(['status' => BookingStatus::Completed]);

        $listener = new NotifyPartiesOnBookingCompleted;
        $listener->handle(new BookingCompleted($this->booking));

        $notification = Notification::where('user_id', $this->producerUser->id)
            ->where('type', 'booking_completed')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('terminé', $notification->data['message']);
        $this->assertEquals("/producer/bookings/{$this->booking->id}", $notification->data['url']);
    }

    public function test_booking_cancelled_by_producer_notifies_face_with_no_penalty_message(): void
    {
        $this->booking->update(['status' => BookingStatus::CancelledByProducer]);

        $listener = new NotifyPartyOnBookingCancelled;
        $listener->handle(new BookingCancelled($this->booking));

        $notification = Notification::where('user_id', $this->faceUser->id)
            ->where('type', 'booking_cancelled')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('annulé', $notification->data['message']);
        $this->assertStringContainsString("n'êtes pas pénalisé", $notification->data['message']);
        $this->assertEquals("/face/bookings/{$this->booking->id}", $notification->data['url']);
    }

    public function test_booking_cancelled_by_face_notifies_producer(): void
    {
        $this->booking->update(['status' => BookingStatus::CancelledByFace]);

        $listener = new NotifyPartyOnBookingCancelled;
        $listener->handle(new BookingCancelled($this->booking));

        $notification = Notification::where('user_id', $this->producerUser->id)
            ->where('type', 'booking_cancelled')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('annulé', $notification->data['message']);
        $this->assertEquals("/producer/bookings/{$this->booking->id}", $notification->data['url']);
    }

    public function test_shared_notification_endpoint_accessible_by_face(): void
    {
        Notification::create([
            'user_id' => $this->faceUser->id,
            'type' => 'booking_received',
            'data' => ['message' => 'Test', 'booking_id' => $this->booking->id],
        ]);

        $this->actingAs($this->faceUser)
            ->getJson('/api/v1/me/notifications')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'type', 'data', 'read_at', 'created_at']]]);
    }

    public function test_shared_notification_endpoint_accessible_by_producer(): void
    {
        Notification::create([
            'user_id' => $this->producerUser->id,
            'type' => 'booking_accepted',
            'data' => ['message' => 'Test', 'booking_id' => $this->booking->id],
        ]);

        $this->actingAs($this->producerUser)
            ->getJson('/api/v1/me/notifications')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'type', 'data', 'read_at', 'created_at']]]);
    }

    public function test_shared_notification_endpoint_returns_401_for_unauthenticated(): void
    {
        $this->getJson('/api/v1/me/notifications')
            ->assertUnauthorized();
    }

    public function test_mark_as_read_returns_403_when_notification_belongs_to_another_user(): void
    {
        // Create a notification belonging to the Producer
        $notification = Notification::create([
            'user_id' => $this->producerUser->id,
            'type' => 'booking_accepted',
            'data' => ['message' => 'Test', 'booking_id' => $this->booking->id],
        ]);

        // Face tries to mark Producer's notification as read → should be 403
        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/me/notifications/{$notification->id}/read")
            ->assertForbidden();

        // Verify notification is still unread
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_as_read_on_shared_endpoint(): void
    {
        Notification::create([
            'user_id' => $this->producerUser->id,
            'type' => 'booking_accepted',
            'data' => ['message' => 'Test 1', 'booking_id' => $this->booking->id],
        ]);
        Notification::create([
            'user_id' => $this->producerUser->id,
            'type' => 'booking_paid',
            'data' => ['message' => 'Test 2', 'booking_id' => $this->booking->id],
        ]);

        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/me/notifications/read-all')
            ->assertOk()
            ->assertJsonFragment(['message' => 'Toutes les notifications marquées comme lues']);

        $this->assertEquals(
            0,
            Notification::where('user_id', $this->producerUser->id)->whereNull('read_at')->count()
        );
    }
}
