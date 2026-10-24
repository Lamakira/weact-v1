<?php

declare(strict_types=1);

namespace Tests\Feature\Whatsapp;

use App\Jobs\SendWhatsappCandidaturesDigest;
use App\Jobs\SendWhatsappMessage;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\Whatsapp\WhatsappService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * Regroupement des « nouvelles candidatures » : au plus un message par mission et par Producteur
 * toutes les 6 h. La 1re part tout de suite et ouvre la fenêtre, un job différé envoie le cumul.
 */
class WhatsappCandidatureGroupingTest extends WhatsappTestCase
{
    private User $producer;

    private Mission $mission;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->fakeMetaAccepts();
        Queue::fake();
        $this->producer = $this->makeProducer('Studio', '0196000000', true, 'Kora');
        $this->mission = $this->missionFor($this->producer, 'Lookbook Wax');
    }

    private function missionFor(User $producer, string $titre): Mission
    {
        return Mission::factory()->create(['producer_id' => $producer->userable_id, 'titre' => $titre]);
    }

    private function candidatureReceived(?User $producer = null, ?Mission $mission = null): void
    {
        $producer ??= $this->producer;
        $mission ??= $this->mission;

        Notification::create([
            'user_id' => $producer->id,
            'type' => 'new_candidature',
            'data' => [
                'message' => "Nouvelle candidature reçue pour la mission \"{$mission->titre}\"",
                'mission_id' => $mission->id,
                'url' => "/producer/missions/{$mission->uuid}?tab=candidatures",
            ],
        ]);
    }

    /** Exécute les jobs d'envoi immédiat en attente (file simulée) et vide la liste. */
    private function runQueuedMessages(): void
    {
        $jobs = Queue::pushed(SendWhatsappMessage::class);
        foreach ($jobs as $job) {
            $job->handle(app(WhatsappService::class));
        }
    }

    public function test_first_candidature_is_sent_immediately_and_opens_a_six_hour_window(): void
    {
        $this->candidatureReceived();

        Queue::assertPushed(SendWhatsappMessage::class, 1);
        Queue::assertPushed(SendWhatsappCandidaturesDigest::class, 1);
        Queue::assertPushed(SendWhatsappCandidaturesDigest::class, function (SendWhatsappCandidaturesDigest $job): bool {
            return $job->userId === $this->producer->id
                && $job->missionId === $this->mission->id
                && $job->delay->equalTo(now()->addHours(6));
        });

        $this->runQueuedMessages();

        $this->assertSame([[
            'template' => 'weact_new_candidatures',
            'to' => '2290196000000',
            'params' => ['Studio', 'Lookbook Wax', '1 nouvelle candidature'],
            'suffix' => "producer/missions/{$this->mission->uuid}?tab=candidatures",
        ]], $this->describeAll());
        $this->assertSame("new_candidatures:{$this->mission->id}", WhatsappMessage::sole()->context);
    }

    public function test_following_candidatures_are_accumulated_not_sent(): void
    {
        $this->candidatureReceived();
        $this->candidatureReceived();
        $this->candidatureReceived();

        Queue::assertPushed(SendWhatsappMessage::class, 1);
        Queue::assertPushed(SendWhatsappCandidaturesDigest::class, 1);
        $this->assertSame(2, Cache::get(WhatsappService::candidatureKeys($this->producer->id, $this->mission->id)[1]));
    }

    public function test_digest_sends_the_accumulated_count_and_opens_the_next_window(): void
    {
        $this->candidatureReceived();
        $this->candidatureReceived();
        $this->candidatureReceived();
        $this->runQueuedMessages();

        Carbon::setTestNow(now()->addHours(6));
        (new SendWhatsappCandidaturesDigest($this->producer->id, $this->mission->id))->handle(app(WhatsappService::class));

        $messages = $this->describeAll();
        $this->assertCount(2, $messages);
        $this->assertSame(['Studio', 'Lookbook Wax', '2 nouvelles candidatures'], $messages[1]['params']);
        $this->assertSame("producer/missions/{$this->mission->uuid}?tab=candidatures", $messages[1]['suffix']);

        // Le cumul est consommé et une nouvelle fenêtre (donc un nouveau rendez-vous) est ouverte.
        $this->assertNull(Cache::get(WhatsappService::candidatureKeys($this->producer->id, $this->mission->id)[1]));
        Queue::assertPushed(SendWhatsappCandidaturesDigest::class, 2);

        // Une candidature arrivée dans cette nouvelle fenêtre est cumulée, pas envoyée.
        $this->candidatureReceived();
        Queue::assertPushed(SendWhatsappMessage::class, 1);
    }

    public function test_digest_without_accumulated_candidatures_closes_the_window(): void
    {
        $this->candidatureReceived();
        $this->runQueuedMessages();

        Carbon::setTestNow(now()->addHours(6));
        (new SendWhatsappCandidaturesDigest($this->producer->id, $this->mission->id))->handle(app(WhatsappService::class));

        $this->assertCount(1, $this->describeAll());
        Queue::assertPushed(SendWhatsappCandidaturesDigest::class, 1);

        // Fenêtre fermée : la candidature suivante repart tout de suite.
        $this->candidatureReceived();
        Queue::assertPushed(SendWhatsappMessage::class, 2);
    }

    public function test_a_candidature_after_the_window_expired_starts_a_new_one(): void
    {
        $this->candidatureReceived();

        Carbon::setTestNow(now()->addHours(6)->addSecond());
        $this->candidatureReceived();

        Queue::assertPushed(SendWhatsappMessage::class, 2);
        Queue::assertPushed(SendWhatsappCandidaturesDigest::class, 2);
    }

    public function test_each_mission_and_each_producer_has_its_own_window(): void
    {
        $otherMission = $this->missionFor($this->producer, 'Spot radio');
        $otherProducer = $this->makeProducer('Atelier', '0195000000', true, 'Nova');
        $theirMission = $this->missionFor($otherProducer, 'Clip vidéo');

        $this->candidatureReceived();
        $this->candidatureReceived($this->producer, $otherMission);
        $this->candidatureReceived($otherProducer, $theirMission);

        Queue::assertPushed(SendWhatsappMessage::class, 3);
        Queue::assertPushed(SendWhatsappCandidaturesDigest::class, 3);
    }

    public function test_a_producer_not_opted_in_gets_neither_message_nor_window(): void
    {
        $silent = $this->makeProducer('Muet', '0194000000', false, 'Plume');
        $mission = $this->missionFor($silent, 'Clip');

        $this->candidatureReceived($silent, $mission);

        Queue::assertNotPushed(SendWhatsappMessage::class);
        Queue::assertNotPushed(SendWhatsappCandidaturesDigest::class);
        $this->assertFalse(Cache::has(WhatsappService::candidatureKeys($silent->id, $mission->id)[0]));
    }

    public function test_digest_sends_nothing_if_the_producer_unsubscribed_meanwhile(): void
    {
        $this->candidatureReceived();
        $this->candidatureReceived();
        $this->runQueuedMessages();
        $this->producer->forceFill(['whatsapp_opt_in_at' => null, 'whatsapp_opt_in_number' => null])->save();

        (new SendWhatsappCandidaturesDigest($this->producer->id, $this->mission->id))->handle(app(WhatsappService::class));

        $this->assertCount(1, $this->describeAll());
        Queue::assertPushed(SendWhatsappCandidaturesDigest::class, 1);
    }

    public function test_digest_gives_the_accumulated_count_back_when_meta_is_down(): void
    {
        $this->candidatureReceived();
        $this->candidatureReceived();
        $this->runQueuedMessages();
        $this->replaceMetaFake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'down']], 503)]);

        try {
            (new SendWhatsappCandidaturesDigest($this->producer->id, $this->mission->id))->handle(app(WhatsappService::class));
            $this->fail('Le job doit échouer pour être rejoué.');
        } catch (\RuntimeException) {
            // attendu : la file rejouera le job
        }

        $this->assertSame(1, Cache::get(WhatsappService::candidatureKeys($this->producer->id, $this->mission->id)[1]));
    }
}
