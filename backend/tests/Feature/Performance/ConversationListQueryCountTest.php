<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Models\Candidature;
use App\Models\Conversation;
use App\Models\Face;
use App\Models\Message;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PERF-B8: the unread counter of the conversation lists comes from the listing query.
 */
class ConversationListQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    /**
     * @return array{0: Conversation, 1: User}
     */
    private function addConversation(int $unreadFromFace, int $readFromFace, int $fromProducer): array
    {
        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $mission = Mission::factory()->create(['producer_id' => $this->producer->id]);
        $candidature = Candidature::factory()->accepted()->create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
        ]);
        $conversation = Conversation::factory()->create(['candidature_id' => $candidature->id]);

        Message::factory()->count($unreadFromFace)->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $faceUser->id,
            'read_at' => null,
        ]);
        Message::factory()->count($readFromFace)->read()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $faceUser->id,
        ]);
        Message::factory()->count($fromProducer)->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->producerUser->id,
            'read_at' => null,
        ]);

        return [$conversation, $faceUser];
    }

    private function queryCount(User $user, string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->getJson($url)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_producer_conversation_list_query_count_is_constant(): void
    {
        $this->addConversation(2, 1, 1);
        $this->addConversation(0, 2, 0);
        $small = $this->queryCount($this->producerUser, '/api/v1/producer/conversations');

        for ($i = 0; $i < 6; $i++) {
            $this->addConversation(1, 0, 2);
        }
        $large = $this->queryCount($this->producerUser, '/api/v1/producer/conversations');

        $this->assertSame($small, $large, "Conversation list queries grew: {$small} -> {$large}");
    }

    public function test_unread_counts_keep_their_semantics_for_both_sides(): void
    {
        [$conversation, $faceUser] = $this->addConversation(unreadFromFace: 3, readFromFace: 2, fromProducer: 4);

        $producerRows = $this->actingAs($this->producerUser)->getJson('/api/v1/producer/conversations')->assertOk()->json('data');
        $this->assertSame(3, collect($producerRows)->firstWhere('id', $conversation->uuid)['unread_count']);

        $faceRows = $this->actingAs($faceUser)->getJson('/api/v1/face/conversations')->assertOk()->json('data');
        $this->assertSame(4, collect($faceRows)->firstWhere('id', $conversation->uuid)['unread_count']);
    }

    public function test_face_conversation_list_query_count_is_constant(): void
    {
        [, $faceUser] = $this->addConversation(1, 1, 1);
        $small = $this->queryCount($faceUser, '/api/v1/face/conversations');

        // More conversations for the SAME Face (one candidature per mission).
        for ($i = 0; $i < 5; $i++) {
            $mission = Mission::factory()->create(['producer_id' => $this->producer->id]);
            $candidature = Candidature::factory()->accepted()->create([
                'face_id' => $faceUser->userable_id,
                'mission_id' => $mission->id,
            ]);
            $conversation = Conversation::factory()->create(['candidature_id' => $candidature->id]);
            Message::factory()->count(2)->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $this->producerUser->id,
                'read_at' => null,
            ]);
        }
        $large = $this->queryCount($faceUser, '/api/v1/face/conversations');

        $this->assertSame($small, $large, "Face conversation list queries grew: {$small} -> {$large}");
    }
}
