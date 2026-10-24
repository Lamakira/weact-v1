<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\Candidature;
use App\Models\Conversation;
use App\Models\Face;
use App\Models\Message;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * GET /bookings/{booking}/face-response et GET /producer/conversations/{conversation}/face-response :
 * comment la Face est prévenue, son délai habituel, et si elle est en retard de réponse.
 */
class FaceResponseNoticeTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private User $producerUser;

    private Face $face;

    private Producer $producer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2030-10-10 12:00:00', 'UTC'));

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
        $this->face = Face::factory()->create(['prenom' => 'Aminata', 'nom' => 'Diallo']);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    private function booking(string $dateDebut = '2030-12-05 10:00:00', BookingStatus $status = BookingStatus::Paid): Booking
    {
        return Booking::factory()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => $status,
            'date_debut' => Carbon::parse($dateDebut, 'UTC'),
        ]);
    }

    private function bookingMessage(Booking $booking, User $sender, int $minutesAgo): void
    {
        BookingMessage::factory()->create([
            'booking_id' => $booking->id,
            'sender_id' => $sender->id,
            'created_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    private function conversation(string $dateTournage = '2030-12-05'): Conversation
    {
        $mission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'date_tournage' => $dateTournage,
        ]);
        $candidature = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $this->face->id,
            'status' => CandidatureStatus::Accepted,
        ]);

        return Conversation::factory()->create(['candidature_id' => $candidature->id]);
    }

    private function conversationMessage(Conversation $conversation, User $sender, int $minutesAgo): void
    {
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'sender_type' => User::class,
            'created_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    private function bookingUrl(Booking $booking): string
    {
        return "/api/v1/bookings/{$booking->uuid}/face-response";
    }

    private function conversationUrl(Conversation $conversation): string
    {
        return "/api/v1/producer/conversations/{$conversation->uuid}/face-response";
    }

    // ─── BOOKING ─────────────────────────────────────────────────

    public function test_booking_payload_in_the_normal_case(): void
    {
        $booking = $this->booking();
        $this->bookingMessage($booking, $this->producerUser, 30);

        $this->actingAs($this->producerUser)
            ->getJson($this->bookingUrl($booking))
            ->assertOk()
            ->assertExactJson(['data' => [
                'face_first_name' => 'Aminata',
                'unread_email_after_minutes' => 60,
                'urgent' => false,
                'typical_response_minutes' => null,
                'overdue' => false,
            ]]);
    }

    public function test_booking_payload_is_urgent_when_shoot_is_near(): void
    {
        // 2030-10-11 à 00:00 (Porto-Novo) = 2030-10-10 23:00 UTC : dans les 48 h
        $booking = $this->booking('2030-10-11 10:00:00');

        $this->actingAs($this->producerUser)
            ->getJson($this->bookingUrl($booking))
            ->assertOk()
            ->assertJsonPath('data.urgent', true)
            ->assertJsonPath('data.unread_email_after_minutes', 15);
    }

    public function test_booking_overdue_after_twelve_hours_but_not_before(): void
    {
        $booking = $this->booking();
        $this->bookingMessage($booking, $this->producerUser, 12 * 60 - 1);

        $this->actingAs($this->producerUser)
            ->getJson($this->bookingUrl($booking))
            ->assertOk()
            ->assertJsonPath('data.overdue', false);

        $this->travelTo(now()->addMinutes(2));

        $this->actingAs($this->producerUser)
            ->getJson($this->bookingUrl($booking))
            ->assertOk()
            ->assertJsonPath('data.overdue', true);
    }

    public function test_booking_overdue_after_two_hours_when_urgent(): void
    {
        $booking = $this->booking('2030-10-11 10:00:00');
        $this->bookingMessage($booking, $this->producerUser, 121);

        $this->actingAs($this->producerUser)
            ->getJson($this->bookingUrl($booking))
            ->assertOk()
            ->assertJsonPath('data.urgent', true)
            ->assertJsonPath('data.overdue', true);
    }

    public function test_booking_not_overdue_when_face_answered_last(): void
    {
        $booking = $this->booking();
        $this->bookingMessage($booking, $this->producerUser, 24 * 60);
        $this->bookingMessage($booking, $this->faceUser, 23 * 60);

        $this->actingAs($this->producerUser)
            ->getJson($this->bookingUrl($booking))
            ->assertOk()
            ->assertJsonPath('data.overdue', false);
    }

    public function test_booking_face_first_name_falls_back_to_la_face(): void
    {
        $this->face->forceFill(['prenom' => '', 'nom' => '', 'username' => ''])->save();
        $booking = $this->booking();

        $this->actingAs($this->producerUser)
            ->getJson($this->bookingUrl($booking))
            ->assertOk()
            ->assertJsonPath('data.face_first_name', 'La Face');
    }

    public function test_booking_forbidden_for_the_face_itself(): void
    {
        $booking = $this->booking();

        $this->actingAs($this->faceUser)
            ->getJson($this->bookingUrl($booking))
            ->assertForbidden();
    }

    public function test_booking_forbidden_for_a_third_party(): void
    {
        $booking = $this->booking();
        $other = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => Producer::factory()->create()->id,
        ]);

        $this->actingAs($other)
            ->getJson($this->bookingUrl($booking))
            ->assertForbidden();
    }

    public function test_booking_forbidden_when_chat_is_not_readable(): void
    {
        $booking = $this->booking(status: BookingStatus::Pending);

        $this->actingAs($this->producerUser)
            ->getJson($this->bookingUrl($booking))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'CHAT_LOCKED');
    }

    // ─── CONVERSATION ────────────────────────────────────────────

    public function test_conversation_payload_in_the_normal_case(): void
    {
        $conversation = $this->conversation();
        $this->conversationMessage($conversation, $this->producerUser, 30);

        $this->actingAs($this->producerUser)
            ->getJson($this->conversationUrl($conversation))
            ->assertOk()
            ->assertExactJson(['data' => [
                'face_first_name' => 'Aminata',
                'unread_email_after_minutes' => 60,
                'urgent' => false,
                'typical_response_minutes' => null,
                'overdue' => false,
            ]]);
    }

    public function test_conversation_payload_is_urgent_when_shoot_is_near(): void
    {
        $conversation = $this->conversation('2030-10-11');

        $this->actingAs($this->producerUser)
            ->getJson($this->conversationUrl($conversation))
            ->assertOk()
            ->assertJsonPath('data.urgent', true)
            ->assertJsonPath('data.unread_email_after_minutes', 15);
    }

    public function test_conversation_overdue_after_twelve_hours_and_two_hours_when_urgent(): void
    {
        $normal = $this->conversation();
        $this->conversationMessage($normal, $this->producerUser, 13 * 60);
        $urgent = $this->conversation('2030-10-11');
        $this->conversationMessage($urgent, $this->producerUser, 3 * 60);
        $recent = $this->conversation();
        $this->conversationMessage($recent, $this->producerUser, 3 * 60);

        $this->actingAs($this->producerUser)
            ->getJson($this->conversationUrl($normal))
            ->assertOk()
            ->assertJsonPath('data.overdue', true);
        $this->actingAs($this->producerUser)
            ->getJson($this->conversationUrl($urgent))
            ->assertOk()
            ->assertJsonPath('data.overdue', true);
        $this->actingAs($this->producerUser)
            ->getJson($this->conversationUrl($recent))
            ->assertOk()
            ->assertJsonPath('data.overdue', false);
    }

    public function test_conversation_not_overdue_when_face_answered_last(): void
    {
        $conversation = $this->conversation();
        $this->conversationMessage($conversation, $this->producerUser, 24 * 60);
        $this->conversationMessage($conversation, $this->faceUser, 23 * 60);

        $this->actingAs($this->producerUser)
            ->getJson($this->conversationUrl($conversation))
            ->assertOk()
            ->assertJsonPath('data.overdue', false);
    }

    public function test_conversation_face_first_name_falls_back_to_la_face(): void
    {
        $this->face->forceFill(['prenom' => '', 'nom' => '', 'username' => ''])->save();
        $conversation = $this->conversation();

        $this->actingAs($this->producerUser)
            ->getJson($this->conversationUrl($conversation))
            ->assertOk()
            ->assertJsonPath('data.face_first_name', 'La Face');
    }

    public function test_conversation_forbidden_for_the_face_itself(): void
    {
        $conversation = $this->conversation();

        $this->actingAs($this->faceUser)
            ->getJson($this->conversationUrl($conversation))
            ->assertForbidden();
    }

    public function test_conversation_forbidden_for_a_third_party(): void
    {
        $conversation = $this->conversation();
        $other = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => Producer::factory()->create()->id,
        ]);

        $this->actingAs($other)
            ->getJson($this->conversationUrl($conversation))
            ->assertForbidden();
    }
}
