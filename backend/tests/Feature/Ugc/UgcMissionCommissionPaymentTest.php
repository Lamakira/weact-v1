<?php

declare(strict_types=1);

namespace Tests\Feature\Ugc;

use App\Enums\MissionStatus;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use App\Services\FedapayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Ugc\Concerns\DispatchesFedapayWebhooks;
use Tests\TestCase;

class UgcMissionCommissionPaymentTest extends TestCase
{
    use DispatchesFedapayWebhooks;
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

    public function test_producer_can_initiate_ugc_mission_commission_payment(): void
    {
        $mission = $this->makePendingPaymentUgcMission();

        $this->mock(FedapayService::class, function ($mock): void {
            $mock->shouldReceive('initiatePaymentForUgcMission')
                ->once()
                ->andReturn(['fedapay_transaction_id' => 701, 'checkout_url' => 'https://fedapay.test/m']);
        });

        $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/pay-commission")
            ->assertOk()
            ->assertJsonPath('checkout_url', 'https://fedapay.test/m');

        $this->assertDatabaseHas('missions', [
            'id' => $mission->id,
            'fedapay_transaction_id' => 701,
        ]);
    }

    public function test_webhook_approved_publishes_mission(): void
    {
        $mission = $this->makePendingPaymentUgcMission();
        $mission->update(['fedapay_transaction_id' => 711]);

        $this->dispatchWebhook('transaction.approved', 711, 'ref_ok');

        $fresh = $mission->fresh();
        $this->assertSame(MissionStatus::Published, $fresh->status);
        $this->assertNotNull($fresh->commission_paid_at);
    }

    public function test_published_ugc_mission_is_not_publicly_visible(): void
    {
        // Depuis UGC 2.1 (FR5), les missions UGC sont exclues des surfaces
        // publiques même une fois publiées — elles ne vivent que dans
        // l'endpoint gated /api/v1/face/ugc/missions.
        $mission = $this->makePendingPaymentUgcMission();
        $mission->update(['fedapay_transaction_id' => 712]);

        // Témoin positif : une mission standard publiée reste visible — prouve que
        // l'absence de l'UGC vient bien de l'exclusion FR5, pas d'une liste cassée.
        $standardMission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
        ]);

        $this->dispatchWebhook('transaction.approved', 712, 'ref_ok');

        $this->assertSame(MissionStatus::Published, $mission->fresh()->status);
        $this->getJson('/api/v1/public/missions')
            ->assertOk()
            ->assertJsonFragment(['id' => $standardMission->uuid])
            ->assertJsonMissing(['id' => $mission->uuid]);
    }

    public function test_webhook_declined_keeps_mission_pending_payment(): void
    {
        $mission = $this->makePendingPaymentUgcMission();
        $mission->update(['fedapay_transaction_id' => 713]);

        $this->dispatchWebhook('transaction.declined', 713, 'ref_ko');

        $fresh = $mission->fresh();
        $this->assertSame(MissionStatus::PendingPayment, $fresh->status);
        $this->assertNull($fresh->commission_paid_at);
    }

    public function test_mission_commission_webhook_is_idempotent(): void
    {
        $mission = $this->makePendingPaymentUgcMission();
        $mission->update(['fedapay_transaction_id' => 714]);

        $this->dispatchWebhook('transaction.approved', 714, 'ref_ok');
        $paidAt = $mission->fresh()->commission_paid_at;

        $this->dispatchWebhook('transaction.approved', 714, 'ref_ok');

        $fresh = $mission->fresh();
        $this->assertSame(MissionStatus::Published, $fresh->status);
        $this->assertNotNull($fresh->commission_paid_at);
        $this->assertEquals(
            $paidAt?->toIso8601String(),
            $fresh->commission_paid_at?->toIso8601String(),
        );
    }

    public function test_commission_status_polling_publishes_when_approved(): void
    {
        $mission = $this->makePendingPaymentUgcMission();
        $mission->update(['fedapay_transaction_id' => 715]);

        $transactionStub = \Mockery::mock(\FedaPay\Transaction::class);
        $transactionStub->status = 'approved';
        $transactionStub->reference = 'ref_poll';

        $this->mock(FedapayService::class, function ($mock) use ($transactionStub): void {
            $mock->shouldReceive('retrieveTransaction')
                ->once()
                ->with(715)
                ->andReturn($transactionStub);
        });

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$mission->uuid}/commission-status")
            ->assertOk()
            ->assertJsonPath('data.status', MissionStatus::Published->value);

        $this->assertSame(MissionStatus::Published, $mission->fresh()->status);
    }

    #[DataProvider('terminalFailedProviderStatuses')]
    public function test_commission_status_polling_exposes_failed_on_terminal_status(string $providerStatus): void
    {
        $mission = $this->makePendingPaymentUgcMission();
        $mission->update(['fedapay_transaction_id' => 716]);

        $transactionStub = \Mockery::mock(\FedaPay\Transaction::class);
        $transactionStub->status = $providerStatus;
        $transactionStub->reference = 'ref_ko';

        $this->mock(FedapayService::class, function ($mock) use ($transactionStub): void {
            $mock->shouldReceive('retrieveTransaction')
                ->once()
                ->with(716)
                ->andReturn($transactionStub);
        });

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$mission->uuid}/commission-status")
            ->assertOk()
            ->assertJsonPath('data.status', MissionStatus::PendingPayment->value)
            ->assertJsonPath('commission_payment_status', 'failed');

        $this->assertSame(MissionStatus::PendingPayment, $mission->fresh()->status);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function terminalFailedProviderStatuses(): array
    {
        return [
            'declined' => ['declined'],
            'canceled' => ['canceled'],
            'refunded' => ['refunded'],
        ];
    }

    public function test_non_owner_cannot_pay_mission_commission(): void
    {
        $mission = $this->makePendingPaymentUgcMission();

        $otherProducer = Producer::factory()->create();
        $otherUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $otherProducer->id,
        ]);

        $this->actingAs($otherUser)
            ->postJson("/api/v1/producer/missions/{$mission->uuid}/pay-commission")
            ->assertForbidden();
    }

    public function test_webhook_approved_on_non_publishable_mission_is_noop_and_marks_processed(): void
    {
        // Régression revue 1.5 : un webhook approved sur une mission UGC qui n'est
        // plus pending_payment (ex. fermée) NE DOIT PAS throw — sinon le job webhook
        // part en retry storm. Il journalise (ops/refund 2.5) et no-op.
        $mission = $this->makePendingPaymentUgcMission();
        $mission->update([
            'fedapay_transaction_id' => 716,
            'status' => MissionStatus::Closed,
        ]);

        $this->dispatchWebhook('transaction.approved', 716, 'ref_late');

        $fresh = $mission->fresh();
        $this->assertSame(MissionStatus::Closed, $fresh->status);
        $this->assertNull($fresh->commission_paid_at);
        // Webhook traité (queue non empoisonnée) : aucun event ne reste 'received'.
        $this->assertDatabaseMissing('fedapay_webhook_events', ['status' => 'received']);
    }

    public function test_webhook_does_not_publish_a_standard_mission_sharing_a_transaction_id(): void
    {
        // Defense-in-depth revue 1.5 : le lookup webhook mission filtre type_mission=ugc.
        // Une mission STANDARD (jamais censée porter missions.fedapay_transaction_id)
        // ne doit jamais être publiée par le settlement UGC, même colonne forcée.
        $standard = $this->producer->missions()->create([
            'titre' => 'Pub standard',
            'description' => 'desc',
            'date_tournage' => now()->addMonth(),
            'profil_recherche' => 'Acteurs',
            'budget' => 50000,
            'date_limite_candidature' => now()->addWeeks(2),
            'nombre_faces_voulu' => 1,
            'type_mission' => 'publicite',
            'genre_voulu' => 'homme',
            'lieu' => 'Cotonou',
            'duree' => '2 jours',
            'status' => MissionStatus::PendingPayment,
        ]);
        $standard->update(['fedapay_transaction_id' => 717]);

        $this->dispatchWebhook('transaction.approved', 717, 'ref_std');

        $fresh = $standard->fresh();
        $this->assertSame(MissionStatus::PendingPayment, $fresh->status);
        $this->assertNull($fresh->commission_paid_at);
    }

    private function makePendingPaymentUgcMission(int $commission = 2500): Mission
    {
        return $this->producer->missions()->create([
            'titre' => 'Appel UGC — Unboxing',
            'description' => 'desc',
            'date_tournage' => now()->addMonth(),
            'profil_recherche' => 'Créatrices',
            'budget' => 0,
            'date_limite_candidature' => now()->addWeeks(2),
            'nombre_faces_voulu' => 3,
            'type_mission' => 'ugc',
            'genre_voulu' => 'femme',
            'lieu' => 'Cotonou',
            'duree' => 'Livrables vidéo',
            'status' => MissionStatus::PendingPayment,
            'type_compensation' => 'product',
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'montant_remuneration' => null,
            'commission_ugc' => $commission,
        ]);
    }
}
