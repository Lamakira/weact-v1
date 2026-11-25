<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Events\NotificationCreated;
use App\Models\Face;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotifyLegalTermsUpdateCommandTest extends TestCase
{
    use RefreshDatabase;

    private const CURRENT = '2026-11-25';

    private function faceUser(?string $consentVersion): User
    {
        $face = Face::factory()->create();

        return User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'consent_version' => $consentVersion,
        ]);
    }

    private function producerUser(?string $consentVersion): User
    {
        $producer = Producer::factory()->create();

        return User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
            'consent_version' => $consentVersion,
        ]);
    }

    private function legalNotifications(): int
    {
        return Notification::where('type', 'legal_terms_updated')->count();
    }

    public function test_without_send_nothing_is_created_and_the_count_is_displayed(): void
    {
        $this->faceUser('2026-08-03');
        $this->producerUser('2026-04-04');

        $this->artisan('legal:notify-terms-update')
            ->expectsOutput('2 utilisateur(s) à notifier. Relancez avec --send pour envoyer.')
            ->assertSuccessful();

        $this->assertSame(0, $this->legalNotifications());
    }

    public function test_send_notifies_outdated_and_null_consent_accounts_only(): void
    {
        $face = $this->faceUser('2026-08-03');
        $producer = $this->producerUser('2026-04-04');
        $nullConsent = $this->faceUser(null);
        $upToDate = $this->producerUser(self::CURRENT);
        $noUserable = User::factory()->create([
            'userable_type' => null,
            'userable_id' => null,
            'consent_version' => null,
        ]);

        $this->artisan('legal:notify-terms-update', ['--send' => true])
            ->expectsOutput('3 notification(s) créée(s).')
            ->assertSuccessful();

        $this->assertSame(3, $this->legalNotifications());
        foreach ([$face, $producer, $nullConsent] as $user) {
            $notification = Notification::where('user_id', $user->id)->where('type', 'legal_terms_updated')->sole();
            $this->assertNotEmpty($notification->uuid);
            $this->assertSame('/cgu', $notification->data['url']);
            $this->assertSame(self::CURRENT, $notification->data['version']);
            $this->assertNull($notification->read_at);
        }
        $this->assertSame(0, Notification::where('user_id', $upToDate->id)->count());
        $this->assertSame(0, Notification::where('user_id', $noUserable->id)->count());
    }

    public function test_a_second_send_creates_nothing_more(): void
    {
        $this->faceUser('2026-08-03');
        $this->producerUser(null);

        $this->artisan('legal:notify-terms-update', ['--send' => true])->assertSuccessful();
        $this->assertSame(2, $this->legalNotifications());

        $this->artisan('legal:notify-terms-update', ['--send' => true])
            ->expectsOutput('0 notification(s) créée(s).')
            ->assertSuccessful();

        $this->assertSame(2, $this->legalNotifications());
    }

    public function test_no_realtime_event_and_no_mail_are_sent(): void
    {
        Event::fake([NotificationCreated::class]);
        Mail::fake();
        $this->faceUser('2026-08-03');
        $this->producerUser('2026-04-04');

        $this->artisan('legal:notify-terms-update', ['--send' => true])->assertSuccessful();

        $this->assertSame(2, $this->legalNotifications());
        Event::assertNotDispatched(NotificationCreated::class);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_the_notification_is_readable_through_the_api_list(): void
    {
        $user = $this->faceUser('2026-08-03');

        $this->artisan('legal:notify-terms-update', ['--send' => true])->assertSuccessful();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/me/notifications');

        $response->assertOk();
        $item = collect($response->json('data'))->firstWhere('type', 'legal_terms_updated');
        $this->assertNotNull($item);
        $this->assertSame('/cgu', $item['data']['url']);
        $this->assertSame(self::CURRENT, $item['data']['version']);
        $this->assertNotEmpty($item['id']);
    }

    public function test_more_than_one_chunk_of_accounts_are_all_notified_exactly_once(): void
    {
        $rows = [];
        for ($i = 1; $i <= 501; $i++) {
            $rows[] = [
                'email' => "bulk{$i}@example.test",
                'password' => 'x',
                'userable_type' => Face::class,
                'userable_id' => 100000 + $i,
                'consent_version' => '2026-08-03',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            User::query()->insert($chunk);
        }

        $this->artisan('legal:notify-terms-update', ['--send' => true])
            ->expectsOutput('501 notification(s) créée(s).')
            ->assertSuccessful();

        $this->assertSame(501, $this->legalNotifications());
        $this->assertSame(501, Notification::where('type', 'legal_terms_updated')->distinct()->count('user_id'));
    }
}
