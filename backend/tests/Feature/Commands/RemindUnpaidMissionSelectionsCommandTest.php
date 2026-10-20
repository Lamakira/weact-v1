<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\MissionStatus;
use App\Mail\MissionPaymentReminderMail;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsPendingMissionSelection;
use Tests\TestCase;

class RemindUnpaidMissionSelectionsCommandTest extends TestCase
{
    use BuildsPendingMissionSelection;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->setUpSelectionProducer();
    }

    public function test_selection_unpaid_for_15h_is_reminded_once(): void
    {
        [$mission, $payment] = $this->pendingSelection(2);
        $this->agePayment($payment->id, 15);

        $this->artisan('missions:remind-unpaid-selections')->assertSuccessful();

        $notification = Notification::where('user_id', $this->producerUser->id)
            ->where('type', 'mission_payment_reminder')
            ->first();
        $this->assertNotNull($notification);
        $this->assertSame("/producer/missions/{$mission->uuid}?tab=candidatures", $notification->data['url']);
        $this->assertStringContainsString("n'est pas encore payée", $notification->data['message']);
        Mail::assertQueued(MissionPaymentReminderMail::class, 1);
        $this->assertNotNull($payment->fresh()->reminder_sent_at);

        $this->artisan('missions:remind-unpaid-selections')->assertSuccessful();

        $this->assertSame(1, Notification::where('type', 'mission_payment_reminder')->count());
        Mail::assertQueued(MissionPaymentReminderMail::class, 1);
    }

    public function test_reminder_does_not_move_the_checkout_resume_anchor(): void
    {
        // updated_at sert d'ancre de « dernière reprise du checkout » (settleOrResetPendingSelection,
        // assessCashCheckout) : une relance n'est pas une reprise et ne doit pas la déplacer.
        [, $payment] = $this->pendingSelection(1);
        $this->agePayment($payment->id, 15);
        $anchor = $payment->fresh()->updated_at;

        $this->artisan('missions:remind-unpaid-selections')->assertSuccessful();

        $fresh = $payment->fresh();
        $this->assertNotNull($fresh->reminder_sent_at);
        $this->assertSame($anchor->toDateTimeString(), $fresh->updated_at->toDateTimeString());
    }

    public function test_selections_outside_the_reminder_window_are_ignored(): void
    {
        [, $young] = $this->pendingSelection(1, transactionId: 777001);
        $this->agePayment($young->id, 10);
        [, $old] = $this->pendingSelection(1, transactionId: 777002);
        $this->agePayment($old->id, 21);

        $this->artisan('missions:remind-unpaid-selections')->assertSuccessful();

        $this->assertSame(0, Notification::where('type', 'mission_payment_reminder')->count());
        Mail::assertNothingQueued();
        $this->assertNull($young->fresh()->reminder_sent_at);
        $this->assertNull($old->fresh()->reminder_sent_at);
    }

    public function test_mission_no_longer_pending_payment_is_ignored(): void
    {
        [$mission, $payment] = $this->pendingSelection(1);
        $this->agePayment($payment->id, 15);
        $mission->update(['status' => MissionStatus::Published]);

        $this->artisan('missions:remind-unpaid-selections')->assertSuccessful();

        $this->assertSame(0, Notification::where('type', 'mission_payment_reminder')->count());
        Mail::assertNothingQueued();
    }
}
