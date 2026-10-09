<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Models\Face;
use App\Models\User;
use App\Notifications\GoogleAccountLinkedNotification;
use App\Notifications\PasswordChangedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * PERF-B7: no SMTP round-trip inside the request or inside the withdrawal lock.
 * Token / signed-link mails are sent after the response (never stored in jobs);
 * plain informative mails are queued.
 */
class EmailDispatchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Record, for every outgoing message, whether it was sent while the kernel
     * was terminating (= after the response).
     *
     * @param  array<string, bool>  $sentAfterResponse  recipient => sent in terminate()
     */
    private function trackSending(array &$sentAfterResponse): void
    {
        Event::listen(MessageSending::class, function (MessageSending $event) use (&$sentAfterResponse): void {
            $inTerminate = collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS))
                ->contains(fn (array $frame): bool => ($frame['function'] ?? '') === 'terminate');

            foreach ($event->message->getTo() as $address) {
                $sentAfterResponse[$address->getAddress()] = $inTerminate;
            }
        });
    }

    public function test_registration_verification_email_goes_out_after_the_response_and_never_through_the_queue(): void
    {
        config(['queue.default' => 'database']);
        $sent = [];
        $this->trackSending($sent);

        $this->postJson('/api/v1/auth/register/face', [
            'nom' => 'Doe',
            'prenom' => 'John',
            'email' => 'john@example.com',
            'date_naissance' => '1995-06-15',
            'password' => 'Password123',
            'accept_cgu' => true,
        ])->assertCreated();

        $this->assertArrayHasKey('john@example.com', $sent, 'Verification mail must still be sent');
        $this->assertTrue($sent['john@example.com'], 'Verification mail must be sent after the response');
        $this->assertSame(0, DB::table('jobs')->count(), 'Nothing carrying a token may be written to the jobs table');
    }

    public function test_email_change_verification_goes_out_after_the_response_and_never_through_the_queue(): void
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => 'old@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        config(['queue.default' => 'database']);
        $sent = [];
        $this->trackSending($sent);

        $this->actingAs($user)->postJson('/api/v1/email/change', [
            'email' => 'new@example.com',
            'password' => 'password123',
        ])->assertOk();

        $this->assertArrayHasKey('new@example.com', $sent, 'Verification mail must still be sent');
        $this->assertTrue($sent['new@example.com'], 'Signed-link mail must be sent after the response');

        $this->assertSame(0, DB::table('jobs')->count(), 'The signed link must never be written to the jobs table');
    }

    public function test_plain_informative_notifications_are_queued(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new PasswordChangedNotification);
        $this->assertInstanceOf(ShouldQueue::class, new GoogleAccountLinkedNotification);

        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        Queue::fake();
        $user->notify(new PasswordChangedNotification);
        $user->notify(new GoogleAccountLinkedNotification);

        Queue::assertPushed(SendQueuedNotifications::class, 2);
    }

    public function test_manual_withdrawal_admin_mail_is_not_sent_while_the_lock_is_held(): void
    {
        config([
            'app.withdrawal_mode' => 'manual',
            'app.admin_email' => 'admin@example.com',
        ]);

        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $faceUser->increment('balance', 50000);

        $lockFreeWhenSending = null;
        Event::listen(MessageSending::class, function () use ($faceUser, &$lockFreeWhenSending): void {
            $probe = Cache::lock("withdrawal_manual_{$faceUser->id}", 10);
            $lockFreeWhenSending = $probe->get();
            if ($lockFreeWhenSending) {
                $probe->release();
            }
        });

        $this->withToken($faceUser->createToken('t')->plainTextToken)
            ->postJson('/api/v1/wallet/withdraw', [
                'amount' => 20000,
                'payment_mode' => 'mtn',
                'phone_number' => '0197000000',
                'phone_country' => 'bj',
            ])->assertOk();

        $this->assertNotNull($lockFreeWhenSending, 'The admin mail must still go out');
        $this->assertTrue($lockFreeWhenSending, 'The admin mail must be dispatched after the withdrawal lock is released');
    }
}
