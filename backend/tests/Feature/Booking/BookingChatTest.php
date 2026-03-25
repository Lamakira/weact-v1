<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Events\BookingMessageSent;
use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BookingChatTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    private User $faceUser;

    private Face $face;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id'   => $this->producer->id,
        ]);

        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id'   => $this->face->id,
        ]);
    }

    // ===========================
    // SEND MESSAGE (POST)
    // ===========================

    public function test_face_can_send_message_in_paid_booking(): void
    {
        Event::fake([BookingMessageSent::class]);

        $booking = Booking::factory()->paid()->create([
            'face_id'     => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->id}/messages", [
                'content' => 'Bonjour, hâte de travailler avec vous !',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.sender_id', $this->faceUser->id)
            ->assertJsonPath('data.content', 'Bonjour, hâte de travailler avec vous !');

        $this->assertDatabaseHas('booking_messages', [
            'booking_id' => $booking->id,
            'sender_id'  => $this->faceUser->id,
            'content'    => 'Bonjour, hâte de travailler avec vous !',
        ]);

        Event::assertDispatched(BookingMessageSent::class);
    }

    public function test_producer_can_send_message_in_paid_booking(): void
    {
        Event::fake([BookingMessageSent::class]);

        $booking = Booking::factory()->paid()->create([
            'face_id'     => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/bookings/{$booking->id}/messages", [
                'content' => 'Voici les détails du tournage.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.sender_id', $this->producerUser->id);

        Event::assertDispatched(BookingMessageSent::class);
    }

    public function test_face_can_send_message_in_confirmed_by_producer_booking(): void
    {
        Event::fake([BookingMessageSent::class]);

        $booking = Booking::factory()->confirmedByProducer()->create([
            'face_id'     => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->id}/messages", [
                'content' => 'Message en cours de mission.',
            ]);

        $response->assertCreated();
        Event::assertDispatched(BookingMessageSent::class);
    }

    public function test_cannot_send_message_in_pending_booking(): void
    {
        $booking = Booking::factory()->create([
            'face_id'     => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status'      => BookingStatus::Pending,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->id}/messages", [
                'content' => 'Chat pas encore ouvert.',
            ]);

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'CHAT_LOCKED');
    }

    public function test_chat_locked_error_body_matches_spec(): void
    {
        $booking = Booking::factory()->create([
            'face_id'     => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status'      => BookingStatus::Pending,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$booking->id}/messages");

        $response->assertForbidden()
            ->assertJsonStructure(['error' => ['code', 'message']])
            ->assertJsonPath('error.code', 'CHAT_LOCKED');
    }

    public function test_cannot_send_message_in_accepted_booking(): void
    {
        $booking = Booking::factory()->create([
            'face_id'     => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status'      => BookingStatus::Accepted,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->id}/messages", [
                'content' => 'Pas encore payé.',
            ]);

        $response->assertForbidden();
    }

    public function test_cannot_list_messages_in_accepted_booking(): void
    {
        $booking = Booking::factory()->create([
            'face_id'     => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status'      => BookingStatus::Accepted,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$booking->id}/messages");

        $response->assertForbidden();
    }

    public function test_cannot_send_message_in_completed_booking(): void
    {
        $booking = Booking::factory()->create([
            'face_id'     => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status'      => BookingStatus::Completed,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->id}/messages", [
                'content' => 'Mission terminée.',
            ]);

        $response->assertForbidden();
    }

    public function test_third_party_cannot_send_message(): void
    {
        $otherUser = User::factory()->create();

        $booking = Booking::factory()->paid()->create([
            'face_id'     => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($otherUser)
            ->postJson("/api/v1/bookings/{$booking->id}/messages", [
                'content' => 'Je suis un intrus.',
            ]);

        $response->assertForbidden();
    }

    public function test_cannot_send_empty_message(): void
    {
        $booking = Booking::factory()->paid()->create([
            'face_id'     => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->id}/messages", [
                'content' => '',
            ]);

        $response->assertUnprocessable();
    }

    public function test_cannot_send_message_exceeding_2000_chars(): void
    {
        $booking = Booking::factory()->paid()->create([
            'face_id'     => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->id}/messages", [
                'content' => str_repeat('a', 2001),
            ]);

        $response->assertUnprocessable();
    }

    public function test_unauthenticated_cannot_send_message(): void
    {
        $booking = Booking::factory()->paid()->create([
            'face_id'     => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/messages", [
            'content' => 'Non authentifié.',
        ]);

        $response->assertUnauthorized();
    }

    // ===========================
    // LIST MESSAGES (GET)
    // ===========================

    public function test_face_can_list_messages_in_paid_booking(): void
    {
        $booking = Booking::factory()->paid()->create([
            'face_id'     => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        BookingMessage::factory()->count(3)->create([
            'booking_id' => $booking->id,
            'sender_id'  => $this->faceUser->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$booking->id}/messages");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_producer_can_list_messages_in_paid_booking(): void
    {
        $booking = Booking::factory()->paid()->create([
            'face_id'     => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        BookingMessage::factory()->count(2)->create([
            'booking_id' => $booking->id,
            'sender_id'  => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/bookings/{$booking->id}/messages");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_both_parties_can_read_messages_in_completed_booking(): void
    {
        $booking = Booking::factory()->create([
            'face_id'     => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status'      => BookingStatus::Completed,
        ]);

        BookingMessage::factory()->create([
            'booking_id' => $booking->id,
            'sender_id'  => $this->faceUser->id,
        ]);

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$booking->id}/messages")
            ->assertOk();

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/bookings/{$booking->id}/messages")
            ->assertOk();
    }

    public function test_cannot_list_messages_in_pending_booking(): void
    {
        $booking = Booking::factory()->create([
            'face_id'     => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status'      => BookingStatus::Pending,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$booking->id}/messages");

        $response->assertForbidden();
    }

    public function test_third_party_cannot_list_messages(): void
    {
        $otherUser = User::factory()->create();

        $booking = Booking::factory()->paid()->create([
            'face_id'     => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($otherUser)
            ->getJson("/api/v1/bookings/{$booking->id}/messages");

        $response->assertForbidden();
    }

    public function test_messages_returned_oldest_first(): void
    {
        $booking = Booking::factory()->paid()->create([
            'face_id'     => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $msg1 = BookingMessage::factory()->create([
            'booking_id' => $booking->id,
            'sender_id'  => $this->faceUser->id,
            'content'    => 'Premier message',
            'created_at' => now()->subMinutes(5),
        ]);

        $msg2 = BookingMessage::factory()->create([
            'booking_id' => $booking->id,
            'sender_id'  => $this->producerUser->id,
            'content'    => 'Deuxième message',
            'created_at' => now()->subMinutes(2),
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$booking->id}/messages");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertEquals($msg1->id, $data[0]['id']);
        $this->assertEquals($msg2->id, $data[1]['id']);
    }

    public function test_message_resource_has_correct_structure(): void
    {
        Event::fake([BookingMessageSent::class]);

        $booking = Booking::factory()->paid()->create([
            'face_id'     => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->id}/messages", [
                'content' => 'Test de structure.',
            ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'booking_id',
                    'sender_id',
                    'sender_name',
                    'content',
                    'is_own_message',
                    'created_at',
                ],
                'message',
            ]);
    }

    public function test_is_own_message_is_true_for_sender(): void
    {
        Event::fake([BookingMessageSent::class]);

        $booking = Booking::factory()->paid()->create([
            'face_id'     => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->id}/messages", [
                'content' => 'Mon message.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.is_own_message', true);
    }

    // ===========================
    // BOOKING MESSAGE SENT EVENT
    // ===========================

    public function test_event_is_dispatched_on_send(): void
    {
        Event::fake([BookingMessageSent::class]);

        $booking = Booking::factory()->paid()->create([
            'face_id'     => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->id}/messages", [
                'content' => 'Événement déclenché ?',
            ])
            ->assertCreated();

        Event::assertDispatched(BookingMessageSent::class, function (BookingMessageSent $event) use ($booking): bool {
            return $event->message->booking_id === $booking->id;
        });
    }
}
