<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\Candidature;
use App\Models\Conversation;
use App\Models\Face;
use App\Models\Message;
use App\Models\Producer;
use App\Models\User;
use App\Services\Messaging\FaceResponsiveness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Règles du service FaceResponsiveness : délai habituel (médiane) et début d'attente.
 * Feature (et non Unit) : les calculs lisent la base.
 */
class FaceResponsivenessTest extends TestCase
{
    use RefreshDatabase;

    private FaceResponsiveness $service;

    private User $faceUser;

    private User $producerUser;

    private Face $face;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2030-10-10 12:00:00', 'UTC'));
        Cache::flush();
        $this->service = app(FaceResponsiveness::class);

        $producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create(['userable_type' => Producer::class, 'userable_id' => $producer->id]);
        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create(['userable_type' => Face::class, 'userable_id' => $this->face->id]);
    }

    /** Fil de mission : liste de [expéditeur ('p'|'f'), minutes avant maintenant]. */
    private function missionThread(array $messages): Conversation
    {
        $candidature = Candidature::factory()->create(['face_id' => $this->face->id]);
        $conversation = Conversation::factory()->create(['candidature_id' => $candidature->id]);

        foreach ($messages as [$who, $minutesAgo]) {
            Message::factory()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $who === 'f' ? $this->faceUser->id : $this->producerUser->id,
                'sender_type' => User::class,
                'created_at' => now()->subMinutes($minutesAgo),
            ]);
        }

        return $conversation;
    }

    private function bookingThread(array $messages): Booking
    {
        $booking = Booking::factory()->paid()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        foreach ($messages as [$who, $minutesAgo]) {
            BookingMessage::factory()->create([
                'booking_id' => $booking->id,
                'sender_id' => $who === 'f' ? $this->faceUser->id : $this->producerUser->id,
                'created_at' => now()->subMinutes($minutesAgo),
            ]);
        }

        return $booking;
    }

    public function test_typical_response_is_null_with_two_threads(): void
    {
        $this->missionThread([['p', 600], ['f', 540]]);
        $this->bookingThread([['p', 600], ['f', 540]]);

        $this->assertNull($this->service->typicalResponseMinutes($this->faceUser));
    }

    public function test_typical_response_is_the_median_over_three_mixed_threads(): void
    {
        $this->missionThread([['p', 600], ['f', 540]]);   // 60 min
        $this->bookingThread([['p', 600], ['f', 500]]);   // 100 min
        $this->bookingThread([['p', 600], ['f', 300]]);   // 300 min

        $this->assertSame(100, $this->service->typicalResponseMinutes($this->faceUser));
    }

    public function test_median_is_rounded_for_an_even_number_of_delays(): void
    {
        $this->missionThread([['p', 600], ['f', 540], ['p', 400], ['f', 399]]); // 60 et 1 min
        $this->bookingThread([['p', 600], ['f', 500]]);                         // 100 min
        $this->bookingThread([['p', 600], ['f', 300]]);                         // 300 min

        // Délais triés : 1, 60, 100, 300 -> médiane (60 + 100) / 2 = 80
        $this->assertSame(80, $this->service->typicalResponseMinutes($this->faceUser));
    }

    public function test_messages_older_than_ninety_days_are_ignored(): void
    {
        $old = 91 * 24 * 60;
        $this->missionThread([['p', $old], ['f', $old - 10]]);
        $this->bookingThread([['p', 600], ['f', 540]]);
        $this->bookingThread([['p', 600], ['f', 500]]);

        // Seuls 2 fils récents exploitables : pas de délai habituel
        $this->assertNull($this->service->typicalResponseMinutes($this->faceUser));
    }

    public function test_several_consecutive_producer_messages_count_as_one_wait(): void
    {
        // 3 messages Producteur d'affilée puis réponse : une seule attente, mesurée depuis le premier (120 min)
        $this->missionThread([['p', 600], ['p', 590], ['p', 580], ['f', 480]]);
        $this->bookingThread([['p', 600], ['f', 500]]); // 100 min
        $this->bookingThread([['p', 600], ['f', 300]]); // 300 min

        // Délais : 120, 100, 300 (et non 4) -> médiane 120
        $this->assertSame(120, $this->service->typicalResponseMinutes($this->faceUser));
    }

    public function test_typical_response_is_cached_for_an_hour(): void
    {
        $this->missionThread([['p', 600], ['f', 540]]);
        $this->bookingThread([['p', 600], ['f', 500]]);
        $this->bookingThread([['p', 600], ['f', 300]]);

        $this->assertSame(100, $this->service->typicalResponseMinutes($this->faceUser));

        $this->bookingThread([['p', 600], ['f', 599]]);
        $this->assertSame(100, $this->service->typicalResponseMinutes($this->faceUser));
    }

    public function test_pending_since_when_face_never_wrote(): void
    {
        $conversation = $this->missionThread([['p', 300], ['p', 200]]);
        $messages = $conversation->messages()->get(['sender_id', 'created_at']);

        $pending = $this->service->pendingSince($messages, $this->faceUser->id);

        $this->assertEquals(now()->subMinutes(300), $pending);
    }

    public function test_pending_since_is_null_when_face_wrote_last(): void
    {
        $conversation = $this->missionThread([['p', 300], ['f', 200]]);
        $messages = $conversation->messages()->get(['sender_id', 'created_at']);

        $this->assertNull($this->service->pendingSince($messages, $this->faceUser->id));
    }

    public function test_pending_since_is_first_producer_message_after_the_face(): void
    {
        $conversation = $this->missionThread([['p', 500], ['f', 400], ['p', 300], ['p', 200]]);
        $messages = $conversation->messages()->get(['sender_id', 'created_at']);

        $pending = $this->service->pendingSince($messages, $this->faceUser->id);

        $this->assertEquals(now()->subMinutes(300), $pending);
        $this->assertNull($this->service->pendingSince([], $this->faceUser->id));
    }

    public function test_bulk_pending_since_matches_the_per_thread_rule(): void
    {
        $answered = $this->missionThread([['p', 500], ['f', 400]]);
        $waiting = $this->missionThread([['p', 500], ['f', 400], ['p', 300], ['p', 200]]);
        $neverAnswered = $this->bookingThread([['p', 700], ['p', 100]]);
        $bookingAnswered = $this->bookingThread([['p', 700], ['f', 100]]);

        $missionResult = $this->service->pendingSinceForCandidatures([
            $answered->candidature_id,
            $waiting->candidature_id,
        ]);
        $bookingResult = $this->service->pendingSinceForBookings([$neverAnswered->id, $bookingAnswered->id]);

        $this->assertSame([$waiting->candidature_id], array_keys($missionResult));
        $this->assertEquals(now()->subMinutes(300), $missionResult[$waiting->candidature_id]);
        $this->assertSame([$neverAnswered->id], array_keys($bookingResult));
        $this->assertEquals(now()->subMinutes(700), $bookingResult[$neverAnswered->id]);
        $this->assertSame([], $this->service->pendingSinceForBookings([]));
    }
}
