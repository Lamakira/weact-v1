<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\User;
use App\Services\MissionPaymentService;
use Illuminate\Support\Facades\DB;

/**
 * Fixtures du lot B1 : sélection de mission en attente de paiement (pending_payment +
 * MissionPayment Pending + entries Pending, candidatures encore Pending), FedaPay mocké.
 * Calquées sur EscrowMissionGuardsTest.
 */
trait BuildsPendingMissionSelection
{
    private Producer $producer;

    private User $producerUser;

    private function setUpSelectionProducer(): void
    {
        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    /**
     * @return array{0: Mission, 1: MissionPayment, 2: list<array{candidature: Candidature, entry: MissionPaymentCandidature, faceUser: User, face: Face}>}
     */
    private function pendingSelection(int $faceCount = 2, ?int $transactionId = 777001): array
    {
        /** @var Mission $mission */
        $mission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::PendingPayment,
        ]);

        $payment = MissionPayment::create([
            'mission_id' => $mission->id,
            'producer_id' => $this->producer->id,
            'nombre_faces_retenues' => $faceCount,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000 * $faceCount,
            'commission_producteur' => 10000 * $faceCount,
            'montant_total_producteur' => 110000 * $faceCount,
            'commission_faces_total' => 10000 * $faceCount,
            'montant_total_faces' => 90000 * $faceCount,
            'fedapay_transaction_id' => $transactionId,
            'status' => MissionPaymentStatus::Pending,
        ]);

        $faces = [];

        for ($i = 0; $i < $faceCount; $i++) {
            $face = Face::factory()->create();
            $faceUser = User::factory()->create([
                'userable_type' => Face::class,
                'userable_id' => $face->id,
            ]);
            $candidature = Candidature::factory()->create([
                'mission_id' => $mission->id,
                'face_id' => $face->id,
                'status' => CandidatureStatus::Pending,
            ]);
            $entry = MissionPaymentCandidature::create([
                'mission_payment_id' => $payment->id,
                'candidature_id' => $candidature->id,
                'face_id' => $face->id,
                'montant_face_recoit' => 90000,
                'escrow_status' => EscrowStatus::Pending,
            ]);

            $faces[] = compact('candidature', 'entry', 'faceUser', 'face');
        }

        return [$mission, $payment, $faces];
    }

    /** Vieillit la row : created_at = il y a $hours h ; updated_at = il y a $updatedMinutesAgo min (défaut : idem created_at). */
    private function agePayment(int $paymentId, float $hours, ?int $updatedMinutesAgo = null): void
    {
        $createdAt = now()->subMinutes((int) round($hours * 60));

        DB::table('mission_payments')->where('id', $paymentId)->update([
            'created_at' => $createdAt,
            'updated_at' => $updatedMinutesAgo !== null ? now()->subMinutes($updatedMinutesAgo) : $createdAt,
        ]);
    }

    private function fedapayReports(string $status, ?\Illuminate\Support\Carbon $createdAt = null, int $id = 777001): void
    {
        $transaction = \Mockery::mock(\FedaPay\Transaction::class);
        $transaction->status = $status;
        $transaction->reference = 'ref_'.$id;
        $transaction->created_at = ($createdAt ?? now()->subMinutes(5))->toIso8601String();
        $transaction->amount = 110000;

        $this->mock(\App\Services\FedapayService::class, function ($mock) use ($id, $transaction): void {
            $mock->shouldReceive('retrieveTransaction')->with($id)->andReturn($transaction);
        });
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private function runWebhook(string $eventId, string $eventName, array $entity): void
    {
        $payload = ['entity' => $entity];
        $webhookEvent = \App\Models\FedapayWebhookEvent::create([
            'fedapay_event_id' => $eventId,
            'event_name' => $eventName,
            'payload' => $payload,
            'status' => 'received',
        ]);

        (new \App\Jobs\HandleFedapayWebhook($webhookEvent->id, $eventName, $payload))->handle(
            app(\App\Services\BookingService::class),
            app(MissionPaymentService::class),
            app(\App\Services\WalletService::class),
            app(\App\Services\FaceSubscriptionPaymentService::class),
        );
    }
}
