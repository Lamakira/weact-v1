<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Events\BookingMessageSent;
use App\Events\ConversationMessageSent;
use App\Jobs\SendWebPush;
use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\Candidature;
use App\Models\Conversation;
use App\Models\Face;
use App\Models\Message;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use App\Support\MessageLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Envoi de position dans les discussions : conversations Face/Producteur et chat booking.
 */
class MessageLocationTest extends TestCase
{
    use RefreshDatabase;

    private const LOCATION = ['lat' => 6.366667, 'lng' => 2.433333, 'accuracy_m' => 15];

    private User $producerUser;

    private User $faceUser;

    private Candidature $candidature;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'webpush.vapid.public_key' => 'public-key-test',
            'webpush.vapid.private_key' => 'private-key-test',
        ]);
        Cache::flush();

        $producer = Producer::factory()->create(['first_name' => 'Awa']);
        $this->producerUser = User::factory()->create(['userable_type' => Producer::class, 'userable_id' => $producer->id]);
        $mission = Mission::factory()->create(['producer_id' => $producer->id, 'status' => MissionStatus::Published]);
        $face = Face::factory()->create(['prenom' => 'Koffi']);
        $this->faceUser = User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);
        $this->candidature = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $face->id,
            'status' => CandidatureStatus::Accepted,
        ]);
        $this->conversation = Conversation::factory()->create(['candidature_id' => $this->candidature->id]);
    }

    private function faceUrl(): string
    {
        return "/api/v1/face/conversations/{$this->conversation->uuid}/messages";
    }

    private function producerUrl(): string
    {
        return "/api/v1/producer/conversations/{$this->conversation->uuid}/messages";
    }

    private function paidBooking(): Booking
    {
        return Booking::factory()->paid()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);
    }

    // ---------------------------------------------------------------- envoi

    public function test_face_can_send_a_location_in_a_conversation(): void
    {
        $this->actingAs($this->faceUser)
            ->postJson($this->faceUrl(), ['location' => self::LOCATION])
            ->assertCreated()
            ->assertJsonPath('data.content', MessageLocation::CONTENT)
            ->assertJsonPath('data.location.lat', 6.366667)
            ->assertJsonPath('data.location.lng', 2.433333)
            ->assertJsonPath('data.location.accuracy_m', 15)
            ->assertJsonPath('data.location.maps_url', 'https://www.google.com/maps/search/?api=1&query=6.366667,2.433333');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->faceUser->id,
            'content' => 'Position partagée',
            'location_accuracy_m' => 15,
        ]);
    }

    public function test_producer_can_send_a_location_in_a_conversation(): void
    {
        $this->actingAs($this->producerUser)
            ->postJson($this->producerUrl(), ['location' => ['lat' => -33.9, 'lng' => -118.25]])
            ->assertCreated()
            ->assertJsonPath('data.location.lat', -33.9)
            ->assertJsonPath('data.location.lng', -118.25)
            ->assertJsonPath('data.location.accuracy_m', null);
    }

    public function test_either_party_can_send_a_location_in_a_booking_chat(): void
    {
        $booking = $this->paidBooking();

        foreach ([$this->faceUser, $this->producerUser] as $user) {
            $this->actingAs($user)
                ->postJson("/api/v1/bookings/{$booking->uuid}/messages", ['location' => self::LOCATION])
                ->assertCreated()
                ->assertJsonPath('data.content', 'Position partagée')
                ->assertJsonPath('data.location.accuracy_m', 15)
                ->assertJsonPath('data.location.maps_url', 'https://www.google.com/maps/search/?api=1&query=6.366667,2.433333');
        }

        $this->assertSame(2, BookingMessage::query()->where('booking_id', $booking->id)->whereNotNull('location_lat')->count());
    }

    public function test_the_content_sent_with_a_location_is_ignored(): void
    {
        $this->actingAs($this->faceUser)
            ->postJson($this->faceUrl(), ['content' => 'Je suis au 12 rue X', 'location' => self::LOCATION])
            ->assertCreated()
            ->assertJsonPath('data.content', 'Position partagée');

        $this->assertDatabaseMissing('messages', ['content' => 'Je suis au 12 rue X']);
    }

    public function test_a_text_message_has_a_null_location(): void
    {
        $this->actingAs($this->faceUser)
            ->postJson($this->faceUrl(), ['content' => 'Bonjour'])
            ->assertCreated()
            ->assertJsonPath('data.location', null);

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/conversations/{$this->conversation->uuid}")
            ->assertOk()
            ->assertJsonPath('data.messages.0.location', null);
    }

    public function test_the_location_is_listed_in_the_booking_messages(): void
    {
        $booking = $this->paidBooking();
        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/messages", ['location' => self::LOCATION])
            ->assertCreated();

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}/messages")
            ->assertOk()
            ->assertJsonPath('data.0.location.lat', 6.366667)
            ->assertJsonPath('data.0.location.lng', 2.433333);
    }

    // ---------------------------------------------------------------- refus

    public function test_a_location_is_refused_when_the_conversation_is_closed(): void
    {
        $this->candidature->update(['status' => CandidatureStatus::Pending]);

        $this->actingAs($this->faceUser)->postJson($this->faceUrl(), ['location' => self::LOCATION])->assertForbidden();
        $this->actingAs($this->producerUser)->postJson($this->producerUrl(), ['location' => self::LOCATION])->assertForbidden();

        $this->assertSame(0, Message::query()->count());
    }

    public function test_a_location_is_refused_when_the_booking_chat_is_locked(): void
    {
        $booking = Booking::factory()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/messages", ['location' => self::LOCATION])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'CHAT_LOCKED');

        $this->assertSame(0, BookingMessage::query()->count());
    }

    public function test_a_non_participant_cannot_send_a_location(): void
    {
        $otherFace = Face::factory()->create();
        $otherFaceUser = User::factory()->create(['userable_type' => Face::class, 'userable_id' => $otherFace->id]);
        $booking = $this->paidBooking();

        $this->actingAs($otherFaceUser)->postJson($this->faceUrl(), ['location' => self::LOCATION])->assertForbidden();
        $this->actingAs($otherFaceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/messages", ['location' => self::LOCATION])
            ->assertForbidden();

        $this->assertSame(0, Message::query()->count());
        $this->assertSame(0, BookingMessage::query()->count());
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidLocations(): array
    {
        return [
            'latitude trop grande' => [['lat' => 90.5, 'lng' => 2.4], 'location.lat'],
            'latitude trop petite' => [['lat' => -91, 'lng' => 2.4], 'location.lat'],
            'longitude trop grande' => [['lat' => 6.3, 'lng' => 180.1], 'location.lng'],
            'longitude trop petite' => [['lat' => 6.3, 'lng' => -181], 'location.lng'],
            'latitude non numérique' => [['lat' => 'abc', 'lng' => 2.4], 'location.lat'],
            'latitude manquante' => [['lng' => 2.4], 'location.lat'],
            'longitude manquante' => [['lat' => 6.3], 'location.lng'],
            'précision négative' => [['lat' => 6.3, 'lng' => 2.4, 'accuracy_m' => -1], 'location.accuracy_m'],
            'précision trop grande' => [['lat' => 6.3, 'lng' => 2.4, 'accuracy_m' => 100001], 'location.accuracy_m'],
            'précision décimale' => [['lat' => 6.3, 'lng' => 2.4, 'accuracy_m' => 12.5], 'location.accuracy_m'],
        ];
    }

    /**
     * @param  array<string, mixed>  $location
     */
    #[DataProvider('invalidLocations')]
    public function test_invalid_coordinates_are_rejected_on_the_three_routes(array $location, string $field): void
    {
        $booking = $this->paidBooking();

        $this->actingAs($this->faceUser)->postJson($this->faceUrl(), ['location' => $location])
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->actingAs($this->producerUser)->postJson($this->producerUrl(), ['location' => $location])
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->actingAs($this->faceUser)->postJson("/api/v1/bookings/{$booking->uuid}/messages", ['location' => $location])
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_neither_content_nor_location_is_rejected_with_a_french_message(): void
    {
        $booking = $this->paidBooking();

        $this->actingAs($this->faceUser)->postJson($this->faceUrl(), [])
            ->assertUnprocessable()->assertJsonValidationErrors(['content' => 'Le message ne peut pas être vide.']);
        $this->actingAs($this->producerUser)->postJson($this->producerUrl(), ['content' => '   '])
            ->assertUnprocessable()->assertJsonValidationErrors(['content' => 'Le message ne peut pas être vide.']);
        $this->actingAs($this->faceUser)->postJson("/api/v1/bookings/{$booking->uuid}/messages", [])
            ->assertUnprocessable()->assertJsonValidationErrors(['content' => 'Le message ne peut pas être vide.']);
    }

    public function test_the_equator_origin_is_accepted(): void
    {
        $this->actingAs($this->faceUser)
            ->postJson($this->faceUrl(), ['location' => ['lat' => 0, 'lng' => 0]])
            ->assertCreated()
            ->assertJsonPath('data.location.maps_url', 'https://www.google.com/maps/search/?api=1&query=0,0');
    }

    // --------------------------------------------- aucune coordonnée en fuite

    public function test_the_push_body_carries_no_coordinate(): void
    {
        Bus::fake([SendWebPush::class]);
        $this->producerUser->updatePushSubscription('https://push.example/p', 'k', 'a');

        $this->actingAs($this->faceUser)->postJson($this->faceUrl(), ['location' => self::LOCATION])->assertCreated();

        Bus::assertDispatched(SendWebPush::class, function (SendWebPush $job): bool {
            return $job->payload['body'] === 'Koffi: Position partagée'
                && ! preg_match('/\d{2,}/', $job->payload['body']);
        });
    }

    public function test_the_booking_push_body_carries_no_coordinate(): void
    {
        Bus::fake([SendWebPush::class]);
        $this->producerUser->updatePushSubscription('https://push.example/p', 'k', 'a');
        $booking = $this->paidBooking();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/messages", ['location' => self::LOCATION])
            ->assertCreated();

        Bus::assertDispatched(SendWebPush::class, fn (SendWebPush $job): bool => $job->payload['body'] === 'Koffi: Position partagée');
    }

    public function test_the_conversation_list_preview_carries_no_coordinate(): void
    {
        $this->actingAs($this->faceUser)->postJson($this->faceUrl(), ['location' => self::LOCATION])->assertCreated();

        $response = $this->actingAs($this->producerUser)->getJson('/api/v1/producer/conversations')->assertOk();

        $response->assertJsonPath('data.0.latest_message.content', 'Position partagée');
        $this->assertStringNotContainsString('6.366667', (string) $response->getContent());
        $this->assertStringNotContainsString('2.433333', (string) $response->getContent());
    }

    public function test_the_booking_thread_preview_carries_no_coordinate(): void
    {
        $booking = $this->paidBooking();
        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/messages", ['location' => self::LOCATION])
            ->assertCreated();

        $response = $this->actingAs($this->producerUser)->getJson('/api/v1/producer/conversations')->assertOk();

        $response->assertJsonPath('data.0.kind', 'booking')
            ->assertJsonPath('data.0.latest_message.content', 'Position partagée');
        $this->assertStringNotContainsString('6.366667', (string) $response->getContent());
        $this->assertStringNotContainsString('2.433333', (string) $response->getContent());
    }

    // ------------------------------------------------------------ temps réel

    public function test_the_conversation_realtime_payload_carries_the_location(): void
    {
        Event::fake([ConversationMessageSent::class]);

        $this->actingAs($this->faceUser)->postJson($this->faceUrl(), ['location' => self::LOCATION])->assertCreated();

        Event::assertDispatched(ConversationMessageSent::class, function (ConversationMessageSent $e): bool {
            $payload = $e->broadcastWith();

            return $payload['content'] === 'Position partagée'
                && $payload['location']['lat'] === 6.366667
                && $payload['location']['lng'] === 2.433333
                && $payload['location']['accuracy_m'] === 15
                && $payload['location']['maps_url'] === 'https://www.google.com/maps/search/?api=1&query=6.366667,2.433333';
        });
    }

    public function test_the_booking_realtime_payload_carries_the_location(): void
    {
        Event::fake([BookingMessageSent::class]);
        $booking = $this->paidBooking();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/messages", ['location' => self::LOCATION])
            ->assertCreated();

        Event::assertDispatched(BookingMessageSent::class, function (BookingMessageSent $e): bool {
            $payload = $e->broadcastWith();

            return $payload['content'] === 'Position partagée'
                && $payload['location']['lat'] === 6.366667
                && $payload['location']['maps_url'] === 'https://www.google.com/maps/search/?api=1&query=6.366667,2.433333';
        });
    }

    public function test_the_realtime_payload_of_a_text_message_has_a_null_location(): void
    {
        Event::fake([ConversationMessageSent::class]);

        $this->actingAs($this->faceUser)->postJson($this->faceUrl(), ['content' => 'Bonjour'])->assertCreated();

        Event::assertDispatched(ConversationMessageSent::class, fn (ConversationMessageSent $e): bool => $e->broadcastWith()['location'] === null);
    }
}
