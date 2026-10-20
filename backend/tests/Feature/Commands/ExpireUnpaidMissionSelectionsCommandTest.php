<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\CandidatureStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Mail\MissionSelectionExpiredMail;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsPendingMissionSelection;
use Tests\TestCase;

class ExpireUnpaidMissionSelectionsCommandTest extends TestCase
{
    use BuildsPendingMissionSelection;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->setUpSelectionProducer();
    }

    public function test_selection_unpaid_after_24h_is_reset_and_producer_is_told(): void
    {
        [$mission, $payment, $faces] = $this->pendingSelection(2);
        $this->agePayment($payment->id, 25);
        $this->fedapayReports('pending', createdAt: now()->subHours(25));

        $this->artisan('missions:expire-unpaid-selections')->assertSuccessful();

        $this->assertSame(0, MissionPayment::where('mission_id', $mission->id)->count());
        $this->assertSame(0, MissionPaymentCandidature::count());
        $this->assertSame(MissionStatus::Published, $mission->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $faces[0]['candidature']->fresh()->status);
        $this->assertSame(CandidatureStatus::Pending, $faces[1]['candidature']->fresh()->status);
        $this->assertDatabaseHas('financial_events', ['type' => 'payment_detached', 'fedapay_ref' => '777001']);

        $notification = \App\Models\Notification::where('user_id', $this->producerUser->id)
            ->where('type', 'mission_selection_reset')
            ->first();
        $this->assertNotNull($notification);
        $this->assertSame('selection_payment_expired', $notification->data['reason']);
        $this->assertSame("/producer/missions/{$mission->uuid}?tab=candidatures", $notification->data['url']);
        $this->assertStringContainsString('Le délai de 24 h pour payer votre sélection', $notification->data['message']);

        Mail::assertQueued(MissionSelectionExpiredMail::class, fn (MissionSelectionExpiredMail $m): bool => $m->hasTo($this->producerUser->email));
    }

    public function test_selection_younger_than_24h_is_untouched(): void
    {
        [$mission, $payment] = $this->pendingSelection(2);
        $this->agePayment($payment->id, 23);

        $this->artisan('missions:expire-unpaid-selections')->assertSuccessful();

        $this->assertSame(1, MissionPayment::where('mission_id', $mission->id)->count());
        $this->assertSame(MissionStatus::PendingPayment, $mission->fresh()->status);
        Mail::assertNothingQueued();
    }

    public function test_selection_paid_at_fedapay_is_settled_not_reset(): void
    {
        [$mission, $payment] = $this->pendingSelection(2);
        $this->agePayment($payment->id, 25);
        $this->fedapayReports('approved', createdAt: now()->subHours(25));

        $this->artisan('missions:expire-unpaid-selections')->assertSuccessful();

        $this->assertSame(MissionPaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(MissionStatus::Closed, $mission->fresh()->status);
        Mail::assertNotQueued(MissionSelectionExpiredMail::class);
        $this->assertDatabaseMissing('notifications', ['type' => 'mission_selection_reset']);
    }

    public function test_checkout_resumed_less_than_an_hour_ago_is_left_alone(): void
    {
        [$mission, $payment] = $this->pendingSelection(2);
        $this->agePayment($payment->id, 25, updatedMinutesAgo: 30);
        $this->fedapayReports('pending', createdAt: now()->subHours(25));

        $this->artisan('missions:expire-unpaid-selections')->assertSuccessful();

        $this->assertSame(1, MissionPayment::where('mission_id', $mission->id)->count());
        $this->assertSame(MissionStatus::PendingPayment, $mission->fresh()->status);
        Mail::assertNotQueued(MissionSelectionExpiredMail::class);
    }

    public function test_fedapay_failure_leaves_the_selection_untouched_and_succeeds(): void
    {
        [$mission, $payment] = $this->pendingSelection(2);
        $this->agePayment($payment->id, 25);

        $this->mock(\App\Services\FedapayService::class, function ($mock): void {
            $mock->shouldReceive('retrieveTransaction')->andThrow(new \RuntimeException('FedaPay down'));
        });

        $this->artisan('missions:expire-unpaid-selections')->assertSuccessful();

        $this->assertSame(1, MissionPayment::where('mission_id', $mission->id)->count());
        $this->assertSame(MissionStatus::PendingPayment, $mission->fresh()->status);
        Mail::assertNotQueued(MissionSelectionExpiredMail::class);
    }

    public function test_selection_without_transaction_is_reset(): void
    {
        [$mission, $payment] = $this->pendingSelection(2, transactionId: null);
        $this->agePayment($payment->id, 25);

        $this->artisan('missions:expire-unpaid-selections')->assertSuccessful();

        $this->assertSame(0, MissionPayment::where('mission_id', $mission->id)->count());
        $this->assertSame(MissionStatus::Published, $mission->fresh()->status);
        $this->assertDatabaseMissing('financial_events', ['type' => 'payment_detached']);
        Mail::assertQueued(MissionSelectionExpiredMail::class);
    }

    public function test_late_approval_after_expiry_credits_the_producer_wallet_once(): void
    {
        [$mission, $payment] = $this->pendingSelection(2);
        $this->agePayment($payment->id, 25);
        $this->fedapayReports('pending', createdAt: now()->subHours(25));

        $this->artisan('missions:expire-unpaid-selections')->assertSuccessful();
        $this->assertSame(0, MissionPayment::where('mission_id', $mission->id)->count());

        \Illuminate\Support\Facades\Log::spy();

        $this->runWebhook('evt_expired_late_1', 'transaction.approved', ['id' => 777001, 'reference' => 'ref_late', 'amount' => 220000]);

        \Illuminate\Support\Facades\Log::shouldNotHaveReceived('critical');
        $this->assertSame(220000, (int) $this->producerUser->fresh()->balance);
        $this->assertSame(MissionStatus::Published, $mission->fresh()->status);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->producerUser->id,
            'type' => 'mission_detached_payment_credited',
        ]);

        $this->runWebhook('evt_expired_late_2', 'transaction.transferred', ['id' => 777001, 'reference' => 'ref_late', 'amount' => 220000]);
        $this->assertSame(220000, (int) $this->producerUser->fresh()->balance);
        $this->assertSame(1, WalletTransaction::where('user_id', $this->producerUser->id)->count());
    }
}
