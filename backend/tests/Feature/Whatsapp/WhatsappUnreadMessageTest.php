<?php

declare(strict_types=1);

namespace Tests\Feature\Whatsapp;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Mail\UnreadMessagesMail;
use App\Models\BookingMessage;
use App\Models\Candidature;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Mission;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * Message non lu : WhatsApp À LA PLACE de l'e-mail pour un destinataire inscrit ; l'e-mail reste le
 * repli (échec de l'API, non inscrit, fonctionnalité indisponible). Verrou et délai de 24 h inchangés.
 * Les cas e-mail purs restent couverts par Tests\Feature\Messaging\RemindUnreadMessagesCommandTest.
 */
class WhatsappUnreadMessageTest extends WhatsappTestCase
{
    private User $face;

    private User $producer;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->fakeMetaAccepts();
        $this->face = $this->makeFace('Awa', '0197451233', true, 'Kone');
        $this->producer = $this->makeProducer('Studio', '0196000000', true, 'Kora');
    }

    private function conversation(): Conversation
    {
        $mission = Mission::factory()->create([
            'producer_id' => $this->producer->userable_id,
            'status' => MissionStatus::Published,
            'titre' => 'Lookbook Wax',
        ]);
        $candidature = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $this->face->userable_id,
            'status' => CandidatureStatus::Accepted,
        ]);

        return Conversation::factory()->create(['candidature_id' => $candidature->id]);
    }

    private function unreadFrom(Conversation $conversation, User $sender): void
    {
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'sender_type' => User::class,
            'content' => 'Mon numéro est le 0197000000',
            'read_at' => null,
            'created_at' => now()->subMinutes(90),
        ]);
    }

    private function runCommand(): void
    {
        $this->artisan('messages:remind-unread')->assertSuccessful();
    }

    private function emailsTo(User $user): int
    {
        return Mail::queued(UnreadMessagesMail::class, fn (UnreadMessagesMail $m) => $m->hasTo($user->email))->count();
    }

    public function test_opted_in_face_gets_whatsapp_instead_of_the_email(): void
    {
        $conversation = $this->conversation();
        $this->unreadFrom($conversation, $this->producer);

        $this->runCommand();

        $this->assertSame([[
            'template' => 'weact_unread_message',
            'to' => '2290197451233',
            'params' => ['Awa', 'Studio Kora', 'Lookbook Wax'],
            'suffix' => "face/conversations/{$conversation->uuid}",
        ]], $this->describeAll());
        $this->assertSame(0, $this->emailsTo($this->face));
        $this->assertSame("unread_message:conversation:{$conversation->id}", WhatsappMessage::sole()->context);
        $this->assertSame('sent', WhatsappMessage::sole()->status);
    }

    public function test_opted_in_producer_gets_whatsapp_for_a_message_from_the_face(): void
    {
        $conversation = $this->conversation();
        $this->unreadFrom($conversation, $this->face);

        $this->runCommand();

        $this->assertSame([[
            'template' => 'weact_unread_message',
            'to' => '2290196000000',
            'params' => ['Studio', 'Awa Kone', 'Lookbook Wax'],
            'suffix' => "producer/conversations/{$conversation->uuid}",
        ]], $this->describeAll());
        $this->assertSame(0, $this->emailsTo($this->producer));
    }

    public function test_booking_chat_uses_the_booking_kind_suffix(): void
    {
        $booking = $this->makeBooking($this->face, $this->producer, '2030-12-05', BookingStatus::Paid);
        BookingMessage::factory()->create([
            'booking_id' => $booking->id,
            'sender_id' => $this->producer->id,
            'read_at' => null,
            'created_at' => now()->subMinutes(90),
        ]);

        $this->runCommand();

        $this->assertSame([[
            'template' => 'weact_unread_message',
            'to' => '2290197451233',
            'params' => ['Awa', 'Studio Kora', 'Booking · Clip musical · 05/12/2030'],
            'suffix' => "face/conversations/{$booking->uuid}?kind=booking",
        ]], $this->describeAll());
        $this->assertSame(0, $this->emailsTo($this->face));
    }

    public function test_the_24_hour_lock_still_applies_to_whatsapp(): void
    {
        $conversation = $this->conversation();
        $this->unreadFrom($conversation, $this->producer);

        $this->runCommand();
        $this->runCommand();

        $this->assertCount(1, $this->describeAll());
        $this->assertSame(1, DB::table('message_unread_email_logs')->where('user_id', $this->face->id)->count());
    }

    public function test_the_email_is_the_fallback_when_meta_refuses_the_message(): void
    {
        $this->replaceMetaFake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 190, 'message' => 'Invalid token']], 401)]);
        $conversation = $this->conversation();
        $this->unreadFrom($conversation, $this->producer);

        $this->runCommand();

        $this->assertSame(1, $this->emailsTo($this->face));
        $this->assertSame('failed', WhatsappMessage::sole()->status);
        $this->assertSame('190', WhatsappMessage::sole()->error_code);
    }

    public function test_the_email_is_the_fallback_when_meta_does_not_answer(): void
    {
        $this->replaceMetaFake(fn () => throw new ConnectionException('cURL error 28: timeout'));
        $conversation = $this->conversation();
        $this->unreadFrom($conversation, $this->producer);

        $this->runCommand();

        $this->assertSame(1, $this->emailsTo($this->face));
        $this->assertSame('failed', WhatsappMessage::sole()->status);
    }

    public function test_a_user_not_opted_in_gets_the_email(): void
    {
        $this->face = $this->makeFace('Binta', '0198000000', false, 'Sow');
        $conversation = $this->conversation();
        $this->unreadFrom($conversation, $this->producer);

        $this->runCommand();

        $this->assertSame(1, $this->emailsTo($this->face));
        Http::assertNothingSent();
    }

    public function test_the_email_is_sent_when_the_feature_is_unavailable(): void
    {
        config(['whatsapp.enabled' => false]);
        $conversation = $this->conversation();
        $this->unreadFrom($conversation, $this->producer);

        $this->runCommand();

        $this->assertSame(1, $this->emailsTo($this->face));
        Http::assertNothingSent();
        $this->assertSame(0, WhatsappMessage::count());
    }
}
