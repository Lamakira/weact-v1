<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Events\ConversationUpdated;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Chats de booking dans la zone « Messages » : lecture, compteur, liste fusionnée, temps réel.
 */
class BookingThreadsTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private User $producerUser;

    private Face $face;

    private Producer $producer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    private function booking(BookingStatus $status = BookingStatus::Paid, array $overrides = []): Booking
    {
        return Booking::factory()->create($overrides + [
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => $status,
        ]);
    }

    private function bookingMessage(Booking $booking, User $sender, bool $read = false, ?Carbon $at = null): BookingMessage
    {
        return BookingMessage::factory()->create([
            'booking_id' => $booking->id,
            'sender_id' => $sender->id,
            'read_at' => $read ? now() : null,
            'created_at' => $at ?? now(),
            'updated_at' => $at ?? now(),
        ]);
    }

    private function conversation(?Carbon $messageAt = null, bool $unreadFromProducer = false): Conversation
    {
        $mission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
        ]);
        $candidature = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $this->face->id,
            'status' => CandidatureStatus::Accepted,
        ]);
        $conversation = Conversation::factory()->create(['candidature_id' => $candidature->id]);

        if ($messageAt !== null) {
            Message::factory()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $this->producerUser->id,
                'sender_type' => User::class,
                'read_at' => $unreadFromProducer ? null : now(),
                'created_at' => $messageAt,
            ]);
        }

        return $conversation;
    }

    public function test_migration_backfill_marks_existing_booking_messages_as_read(): void
    {
        $booking = $this->booking();
        $message = $this->bookingMessage($booking, $this->producerUser, false, Carbon::parse('2026-09-01 10:00:00'));

        // Rejoue le rétroactif de la migration sur une ligne « d'avant » (read_at absent)
        DB::table('booking_messages')->where('id', $message->id)->update(['read_at' => null]);
        $migration = require database_path('migrations/2026_10_20_000001_add_read_at_to_booking_messages_table.php');
        $migration->backfill();

        $this->assertSame(
            '2026-09-01 10:00:00',
            Carbon::parse(DB::table('booking_messages')->where('id', $message->id)->value('read_at'))->format('Y-m-d H:i:s'),
        );
    }

    public function test_getting_the_messages_does_not_mark_them_read(): void
    {
        $booking = $this->booking();
        $fromProducer = $this->bookingMessage($booking, $this->producerUser);

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}/messages")
            ->assertOk();

        // Le marquage lu passe uniquement par POST .../messages/read (fil réellement visible)
        $this->assertNull($fromProducer->fresh()->read_at);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/messages/read")
            ->assertOk()
            ->assertJsonPath('data.marked', 1);
        $this->assertNotNull($fromProducer->fresh()->read_at);
    }

    public function test_type_contenu_comparison_is_case_sensitive_like_the_policy(): void
    {
        // Booking cash dont le type vaut « ugc » (minuscules) : pour BookingPolicy ce n'est PAS de l'UGC.
        // Accepted n'est lisible que pour l'UGC : le fil ne doit être ni compté ni listé.
        $lower = $this->booking(BookingStatus::Accepted, ['type_contenu' => 'ugc']);
        $this->bookingMessage($lower, $this->producerUser);
        // Même type en minuscules mais payé : lisible comme un booking cash.
        $paid = $this->booking(BookingStatus::Paid, ['type_contenu' => 'ugc']);
        $this->bookingMessage($paid, $this->producerUser);

        $this->actingAs($this->faceUser)->getJson("/api/v1/bookings/{$lower->uuid}/messages")->assertForbidden();
        $this->actingAs($this->faceUser)->getJson("/api/v1/bookings/{$paid->uuid}/messages")->assertOk();

        $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/conversations/unread-count')
            ->assertJsonPath('data.count', 1);
        $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/conversations')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $paid->uuid);
    }

    public function test_mark_read_route_is_idempotent_and_returns_the_new_total(): void
    {
        $a = $this->booking();
        $b = $this->booking();
        $this->bookingMessage($a, $this->producerUser);
        $this->bookingMessage($b, $this->producerUser);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$a->uuid}/messages/read")
            ->assertOk()
            ->assertJsonPath('data.marked', 1)
            ->assertJsonPath('data.unread_conversations_count', 1);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$a->uuid}/messages/read")
            ->assertOk()
            ->assertJsonPath('data.marked', 0);
    }

    public function test_mark_read_is_refused_to_a_non_party(): void
    {
        $booking = $this->booking();
        $this->bookingMessage($booking, $this->producerUser);
        $stranger = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => Face::factory()->create()->id,
        ]);

        $this->actingAs($stranger)
            ->postJson("/api/v1/bookings/{$booking->uuid}/messages/read")
            ->assertForbidden();
    }

    public function test_unread_count_sums_mission_and_booking_threads_not_messages(): void
    {
        $this->conversation(now(), true);
        $booking = $this->booking();
        $this->bookingMessage($booking, $this->producerUser);
        $this->bookingMessage($booking, $this->producerUser);
        $this->bookingMessage($this->booking(), $this->producerUser, true);
        $this->bookingMessage($this->booking(), $this->faceUser);

        $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/conversations/unread-count')
            ->assertOk()
            ->assertExactJson(['data' => ['count' => 2]]);

        // Côté Producer : seul le message envoyé par la Face compte
        $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/conversations/unread-count')
            ->assertOk()
            ->assertExactJson(['data' => ['count' => 1]]);
    }

    public function test_unread_count_ignores_booking_threads_not_readable_yet(): void
    {
        $this->bookingMessage($this->booking(BookingStatus::Pending), $this->producerUser);
        $this->bookingMessage($this->booking(BookingStatus::Accepted), $this->producerUser);
        $this->bookingMessage($this->booking(BookingStatus::CancelledByProducer), $this->producerUser);

        $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/conversations/unread-count')
            ->assertJsonPath('data.count', 0);
    }

    public function test_ugc_booking_thread_is_readable_from_accepted(): void
    {
        $ugc = $this->booking(BookingStatus::Accepted, ['type_contenu' => 'UGC']);
        $this->bookingMessage($ugc, $this->producerUser);

        $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/conversations/unread-count')
            ->assertJsonPath('data.count', 1);
    }

    public function test_list_merges_missions_and_bookings_sorted_by_last_message(): void
    {
        $oldConv = $this->conversation(now()->subHours(5), true);
        $booking = $this->booking(BookingStatus::Paid, [
            'type_contenu' => 'Clip musical',
            'date_debut' => Carbon::parse('2026-12-05 10:00:00', 'UTC'),
        ]);
        $this->bookingMessage($booking, $this->producerUser, false, now()->subHours(2));
        $newConv = $this->conversation(now()->subHour(), true);

        $response = $this->actingAs($this->faceUser)->getJson('/api/v1/face/conversations')->assertOk();

        $response->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $newConv->uuid)
            ->assertJsonPath('data.0.kind', 'mission')
            ->assertJsonPath('data.1.id', $booking->uuid)
            ->assertJsonPath('data.1.kind', 'booking')
            ->assertJsonPath('data.1.unread_count', 1)
            ->assertJsonPath('data.1.other_participant.type', 'producer')
            ->assertJsonPath('data.1.other_participant.id', $this->producer->uuid)
            ->assertJsonPath('data.1.context', null)
            ->assertJsonPath('data.2.id', $oldConv->uuid)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.unread_conversations_count', 3);

        $title = $response->json('data.1.mission_title');
        $this->assertStringStartsWith('Booking · Clip musical · ', $title);
        $this->assertMatchesRegularExpression('#\d{2}/\d{2}/2026$#', $title);
    }

    public function test_list_is_paginated_over_the_merged_set(): void
    {
        // 10 conversations + 10 bookings entrelacés : 20 fils, pages de 15
        for ($i = 0; $i < 10; $i++) {
            $this->conversation(now()->subMinutes(100 - $i * 10));
            $this->bookingMessage($this->booking(), $this->producerUser, true, now()->subMinutes(95 - $i * 10));
        }

        $page1 = $this->actingAs($this->faceUser)->getJson('/api/v1/face/conversations?page=1')->assertOk();
        $page2 = $this->actingAs($this->faceUser)->getJson('/api/v1/face/conversations?page=2')->assertOk();

        $page1->assertJsonCount(15, 'data')->assertJsonPath('meta.total', 20)->assertJsonPath('meta.last_page', 2);
        $page2->assertJsonCount(5, 'data');

        $dates = collect($page1->json('data'))->concat($page2->json('data'))->map(
            fn (array $t) => $t['latest_message']['created_at']
        );
        $this->assertSame($dates->sortDesc()->values()->all(), $dates->values()->all());
        $this->assertCount(20, collect($page1->json('data'))->concat($page2->json('data'))->pluck('id')->unique());
    }

    public function test_list_has_a_constant_query_count(): void
    {
        $measure = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->faceUser)->getJson('/api/v1/face/conversations')->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $this->bookingMessage($this->booking(), $this->producerUser);
        $this->conversation(now(), true);
        $few = $measure();

        for ($i = 0; $i < 4; $i++) {
            $this->bookingMessage($this->booking(), $this->producerUser);
            $this->conversation(now(), true);
        }

        $this->assertSame($few, $measure());
    }

    public function test_booking_threads_are_listed_for_the_producer_too(): void
    {
        $booking = $this->booking();
        $this->bookingMessage($booking, $this->faceUser);

        $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $booking->uuid)
            ->assertJsonPath('data.0.other_participant.type', 'face')
            ->assertJsonPath('data.0.unread_count', 1);
    }

    public function test_list_hides_threads_without_message_non_readable_statuses_and_foreign_bookings(): void
    {
        $this->booking(); // payé mais sans message
        $this->bookingMessage($this->booking(BookingStatus::Pending), $this->producerUser);
        $this->bookingMessage($this->booking(BookingStatus::Accepted), $this->producerUser);

        $foreign = Booking::factory()->paid()->create();
        BookingMessage::factory()->create(['booking_id' => $foreign->id, 'sender_id' => $foreign->producer_id]);

        $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/conversations')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
        $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/conversations')
            ->assertJsonCount(0, 'data');

        // L'autre Face ne voit que son propre fil, jamais celui du premier couple
        $foreignFace = User::find($foreign->face_id);
        $this->actingAs($foreignFace)
            ->getJson('/api/v1/face/conversations/unread-count')
            ->assertJsonPath('data.count', 1);
        $this->actingAs($foreignFace)
            ->getJson('/api/v1/face/conversations')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $foreign->uuid);
    }

    public function test_sending_a_booking_message_dispatches_the_user_event_with_the_new_totals(): void
    {
        Event::fake([ConversationUpdated::class]);

        // Producer : un autre fil booking non lu (Face -> Producer)
        $other = $this->booking();
        $this->bookingMessage($other, $this->faceUser);
        $current = $this->booking();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$current->uuid}/messages", ['content' => 'Bonjour'])
            ->assertCreated();

        Event::assertDispatched(ConversationUpdated::class, fn (ConversationUpdated $e) => $e->recipientId === $this->producerUser->id
            && $e->kind === 'booking'
            && $e->conversationUuid === $current->uuid
            && $e->unreadCount === 1
            && $e->broadcastWith()['unread_conversations_count'] === 2);
        Event::assertDispatched(ConversationUpdated::class, fn (ConversationUpdated $e) => $e->recipientId === $this->faceUser->id
            && $e->broadcastWith()['unread_conversations_count'] === 0);
    }

    public function test_marking_read_dispatches_the_user_event_for_the_reader_only(): void
    {
        Event::fake([ConversationUpdated::class]);

        $a = $this->booking();
        $b = $this->booking();
        $this->bookingMessage($a, $this->producerUser);
        $this->bookingMessage($b, $this->producerUser);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$a->uuid}/messages/read")
            ->assertOk();

        Event::assertDispatchedTimes(ConversationUpdated::class, 1);
        Event::assertDispatched(ConversationUpdated::class, fn (ConversationUpdated $e) => $e->recipientId === $this->faceUser->id
            && $e->kind === 'booking'
            && $e->broadcastWith()['unread_conversations_count'] === 1);

        // Rien à marquer : aucun nouvel événement
        $this->actingAs($this->faceUser)->postJson("/api/v1/bookings/{$a->uuid}/messages/read")->assertOk();
        Event::assertDispatchedTimes(ConversationUpdated::class, 1);
    }
}
