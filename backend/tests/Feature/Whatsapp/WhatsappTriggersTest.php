<?php

declare(strict_types=1);

namespace Tests\Feature\Whatsapp;

use App\Enums\BookingStatus;
use App\Events\BookingAccepted;
use App\Events\BookingCommissionPaid;
use App\Events\BookingCreated;
use App\Events\BookingPaid;
use App\Events\BookingRefused;
use App\Jobs\SendWhatsappMessage;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

/**
 * Chaque déclencheur envoie le bon modèle, avec les bons paramètres et le bon suffixe d'URL ;
 * rien ne part si le destinataire n'est pas inscrit, si la fonctionnalité est indisponible ou hors allowlist.
 */
class WhatsappTriggersTest extends WhatsappTestCase
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

    public function test_booking_received_sends_the_request_template_to_the_face(): void
    {
        $booking = $this->makeBooking($this->face, $this->producer);

        event(new BookingCreated($booking));

        $this->assertSame([[
            'template' => 'weact_booking_request',
            'to' => '2290197451233',
            // Créé jeudi 10 midi UTC + 72 h = dimanche 13 octobre 12:00 UTC = 13 h au Bénin (avant le tournage du 15).
            'params' => ['Awa', 'Studio Kora', 'mardi 15 octobre', 'dimanche 13 octobre à 13 h'],
            'suffix' => "face/bookings/{$booking->uuid}",
        ]], $this->describeAll());

        $request = $this->sentMessages()[0];
        $this->assertSame('whatsapp', $request['messaging_product']);
        $this->assertSame('template', $request['type']);
        $this->assertSame('fr', $request['template']['language']['code']);
        $this->assertSame('button', $request['template']['components'][1]['type']);
        $this->assertSame('url', $request['template']['components'][1]['sub_type']);
        $this->assertSame('0', $request['template']['components'][1]['index']);
        $this->assertSame('Bearer test-token', $request->header('Authorization')[0]);
    }

    public function test_booking_received_deadline_is_capped_by_the_shooting_day(): void
    {
        // Tournage vendredi 11 octobre : échéance = 8 h le jour du tournage (fuseau métier), avant les 72 h.
        $booking = $this->makeBooking($this->face, $this->producer, '2030-10-11');

        event(new BookingCreated($booking));

        $this->assertSame(['Awa', 'Studio Kora', 'vendredi 11 octobre', 'vendredi 11 octobre à 8 h'], $this->describeAll()[0]['params']);
    }

    public function test_booking_received_is_not_sent_for_a_ugc_request_without_deadline(): void
    {
        $booking = $this->makeBooking($this->face, $this->producer, type: 'UGC');

        event(new BookingCreated($booking));

        Http::assertNothingSent();
        $this->assertSame(0, WhatsappMessage::count());
    }

    public function test_response_reminder_command_sends_the_reminder_template(): void
    {
        $booking = Booking::factory()->create([
            'face_id' => $this->face->id,
            'producer_id' => $this->producer->id,
            'status' => BookingStatus::Pending,
            'type_contenu' => 'Clip musical',
            'date_debut' => Carbon::parse('2030-10-15', 'UTC'),
            'created_at' => now()->subHours(50),
        ]);

        $this->artisan('bookings:remind-face-response')->assertSuccessful();

        $this->assertSame([[
            'template' => 'weact_booking_reminder',
            'to' => '2290197451233',
            // Créé il y a 50 h : échéance = dans 22 h = vendredi 11 octobre 10:00 UTC = 11 h au Bénin.
            'params' => ['Awa', 'Studio Kora', 'vendredi 11 octobre à 11 h'],
            'suffix' => "face/bookings/{$booking->uuid}",
        ]], $this->describeAll());
    }

    public function test_booking_paid_notifies_the_face_only_never_the_producer(): void
    {
        $booking = $this->makeBooking($this->face, $this->producer, status: BookingStatus::Paid);

        event(new BookingPaid($booking));

        // Les deux parties reçoivent une notification in-app, une seule alerte WhatsApp part : celle de la Face.
        $this->assertSame(2, Notification::where('type', 'booking_paid')->count());
        $this->assertSame([[
            'template' => 'weact_payment_secured',
            'to' => '2290197451233',
            'params' => ['Awa', 'Studio Kora', 'Clip musical'],
            'suffix' => "face/bookings/{$booking->uuid}",
        ]], $this->describeAll());
    }

    public function test_ugc_commission_paid_sends_the_payment_secured_template_to_the_face(): void
    {
        $booking = $this->makeBooking($this->face, $this->producer, type: 'UGC');

        event(new BookingCommissionPaid($booking));

        $this->assertSame([[
            'template' => 'weact_payment_secured',
            'to' => '2290197451233',
            'params' => ['Awa', 'Studio Kora', 'UGC'],
            'suffix' => "face/bookings/{$booking->uuid}",
        ]], $this->describeAll());
    }

    public function test_booking_accepted_sends_the_accepted_template_to_the_producer(): void
    {
        $booking = $this->makeBooking($this->face, $this->producer, status: BookingStatus::Accepted);

        event(new BookingAccepted($booking));

        $this->assertSame([[
            'template' => 'weact_booking_accepted',
            'to' => '2290196000000',
            'params' => ['Studio', 'Awa Kone', 'mardi 15 octobre'],
            'suffix' => "producer/bookings/{$booking->uuid}",
        ]], $this->describeAll());
    }

    public function test_booking_refused_sends_the_declined_template_to_the_producer(): void
    {
        $booking = $this->makeBooking($this->face, $this->producer, status: BookingStatus::Refused);

        event(new BookingRefused($booking));

        $this->assertSame([[
            'template' => 'weact_booking_declined',
            'to' => '2290196000000',
            'params' => ['Studio', 'Awa Kone', 'mardi 15 octobre'],
            'suffix' => "producer/bookings/{$booking->uuid}",
        ]], $this->describeAll());
    }

    public function test_automatic_refusal_does_not_claim_the_face_declined(): void
    {
        $booking = $this->makeBooking($this->face, $this->producer, status: BookingStatus::Refused);

        event(new BookingRefused($booking, automatic: true));

        $this->assertSame(1, Notification::where('type', 'booking_refused')->count());
        Http::assertNothingSent();
    }

    public function test_candidature_accepted_after_payment_sends_the_place_paid_template(): void
    {
        [$mission, $candidature] = $this->missionWithCandidature();

        Notification::create([
            'user_id' => $this->face->id,
            'type' => 'candidature_accepted',
            'data' => [
                'mission_title' => $mission->titre,
                'candidature_id' => $candidature->id,
                'place_paid' => true,
                'message' => 'Votre candidature a été acceptée — confirmez votre participation.',
                'url' => "/face/missions/{$mission->uuid}",
            ],
        ]);

        $this->assertSame([[
            'template' => 'weact_mission_place_paid',
            'to' => '2290197451233',
            'params' => ['Awa', 'Studio Kora', 'Lookbook Wax'],
            'suffix' => "face/missions/{$mission->uuid}",
        ]], $this->describeAll());
    }

    public function test_candidature_accepted_without_a_paid_place_sends_nothing(): void
    {
        [$mission, $candidature] = $this->missionWithCandidature();

        // Acceptation produit seul (CandidatureController::accept) : aucune place payée.
        Notification::create([
            'user_id' => $this->face->id,
            'type' => 'candidature_accepted',
            'data' => [
                'mission_title' => $mission->titre,
                'candidature_id' => $candidature->id,
                'message' => 'Votre candidature a été acceptée — confirmez votre participation.',
                'url' => "/face/missions/{$mission->uuid}",
            ],
        ]);

        Http::assertNothingSent();
    }

    public function test_nothing_is_sent_to_a_user_who_is_not_opted_in(): void
    {
        $face = $this->makeFace('Binta', '0198000000', optIn: false);
        $booking = $this->makeBooking($face, $this->producer);

        event(new BookingCreated($booking));

        Http::assertNothingSent();
        $this->assertSame(0, WhatsappMessage::count());
    }

    public function test_nothing_is_sent_once_the_opt_in_lapsed_by_a_number_change(): void
    {
        $booking = $this->makeBooking($this->face, $this->producer);
        Face::whereKey($this->face->userable_id)->update(['whatsapp_number' => '0191111111']);

        event(new BookingCreated($booking));

        Http::assertNothingSent();
    }

    public function test_nothing_is_dispatched_when_the_feature_is_unavailable(): void
    {
        Queue::fake();
        $booking = $this->makeBooking($this->face, $this->producer);

        foreach ([
            ['whatsapp.enabled' => false],
            ['whatsapp.phone_number_id' => null],
            ['whatsapp.access_token' => ''],
        ] as $override) {
            config($override);
            event(new BookingCreated($booking));
            $this->enableWhatsapp();
        }

        Queue::assertNotPushed(SendWhatsappMessage::class);
        Http::assertNothingSent();
        $this->assertSame(0, WhatsappMessage::count());
    }

    public function test_types_outside_the_allowlist_send_nothing(): void
    {
        $booking = $this->makeBooking($this->face, $this->producer);

        Notification::create([
            'user_id' => $this->face->id,
            'type' => 'booking_cancelled',
            'data' => ['message' => 'Annulé', 'booking_id' => $booking->id, 'url' => "/face/bookings/{$booking->uuid}"],
        ]);

        Http::assertNothingSent();
    }

    public function test_a_recipient_without_a_display_name_is_skipped(): void
    {
        $producer = Producer::factory()->agency()->create(['agency_name' => '', 'whatsapp_number' => '0196111111']);
        $nameless = $this->optIn(User::factory()->create(['userable_type' => Producer::class, 'userable_id' => $producer->id]));
        $booking = $this->makeBooking($this->face, $nameless, status: BookingStatus::Accepted);

        event(new BookingAccepted($booking));

        Http::assertNothingSent();
    }

    public function test_notification_with_an_unsafe_url_sends_nothing(): void
    {
        $booking = $this->makeBooking($this->face, $this->producer);

        Notification::create([
            'user_id' => $this->face->id,
            'type' => 'booking_received',
            'data' => ['message' => 'x', 'booking_id' => $booking->id, 'url' => 'https://evil.example/phish'],
        ]);

        Http::assertNothingSent();
    }

    public function test_the_journal_records_queued_then_sent_with_the_wamid_and_no_message_text(): void
    {
        $this->replaceMetaFake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.ABC123']]], 200)]);
        $booking = $this->makeBooking($this->face, $this->producer);

        event(new BookingCreated($booking));

        $log = WhatsappMessage::sole();
        $this->assertSame($this->face->id, $log->user_id);
        $this->assertSame('weact_booking_request', $log->template);
        $this->assertSame('2290197451233', $log->to);
        $this->assertSame("booking_received:{$booking->id}", $log->context);
        $this->assertSame('wamid.ABC123', $log->wamid);
        $this->assertSame('sent', $log->status);
        $this->assertNull($log->error_code);

        // Aucun champ ne peut porter un texte de message.
        $this->assertEqualsCanonicalizing(
            ['id', 'user_id', 'template', 'to', 'context', 'wamid', 'status', 'error_code', 'error_message', 'status_updated_at', 'created_at', 'updated_at'],
            Schema::getColumnListing('whatsapp_messages'),
        );
    }

    public function test_a_meta_refusal_is_journaled_as_failed_without_breaking_the_notification(): void
    {
        $this->replaceMetaFake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 131026, 'message' => 'Message undeliverable']], 400)]);
        $booking = $this->makeBooking($this->face, $this->producer);

        event(new BookingCreated($booking));

        $this->assertSame(1, Notification::where('type', 'booking_received')->count());
        $log = WhatsappMessage::sole();
        $this->assertSame('failed', $log->status);
        $this->assertSame('131026', $log->error_code);
        $this->assertSame('Message undeliverable', $log->error_message);
        $this->assertNull($log->wamid);
    }

    public function test_a_network_failure_is_journaled_and_never_breaks_the_notification(): void
    {
        $this->replaceMetaFake(fn () => throw new ConnectionException('cURL error 28: timeout'));
        $booking = $this->makeBooking($this->face, $this->producer);

        event(new BookingCreated($booking));

        $this->assertSame(1, Notification::where('type', 'booking_received')->count());
        $log = WhatsappMessage::sole();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('timeout', (string) $log->error_message);
    }

    /**
     * @return array{0: Mission, 1: Candidature}
     */
    private function missionWithCandidature(): array
    {
        $mission = Mission::factory()->create([
            'producer_id' => $this->producer->userable_id,
            'titre' => 'Lookbook Wax',
        ]);
        $candidature = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $this->face->userable_id,
        ]);

        return [$mission, $candidature];
    }
}
