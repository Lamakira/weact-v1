<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\FaceSubscriptionPlan;
use App\Enums\MissionGender;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Enums\UgcSuspensionAppealStatus;
use App\Enums\UgcSuspensionReason;
use App\Events\MissionPublished;
use App\Jobs\SendWebPush;
use App\Listeners\Mission\NotifyFacesOfNewMission;
use App\Mail\NewMissionAvailableMail;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\UgcSuspension;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotifyFacesOfNewMissionTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private User $producerUser;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'webpush.vapid.public_key' => 'public-key-test',
            'webpush.vapid.private_key' => 'private-key-test',
        ]);

        // The observer would run the listener synchronously (sync queue): drive it explicitly.
        Event::fake([MissionPublished::class]);
        Bus::fake([SendWebPush::class]);
        Mail::fake();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
        // The Producer also has a device and an e-mail-eligible profile: must never be notified.
        $this->producerUser->updatePushSubscription('https://fcm.googleapis.com/fcm/send/producer', 'k', 'a');
    }

    /**
     * @param  array<string, mixed>  $faceAttrs
     * @param  array<string, mixed>  $userAttrs
     * @return array{0: Face, 1: User}
     */
    private function makeFace(?FaceSubscriptionPlan $plan = null, bool $push = true, array $faceAttrs = [], array $userAttrs = []): array
    {
        $face = Face::factory()->create($faceAttrs);
        $user = User::factory()->create($userAttrs + [
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        if ($push) {
            $user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/'.$user->id, 'k', 'a');
        }
        if ($plan !== null) {
            FaceSubscription::factory()->create(['face_id' => $face->id, 'plan' => $plan]);
        }

        return [$face, $user];
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function makeMission(array $attrs = []): Mission
    {
        return Mission::factory()->published()->create($attrs + [
            'producer_id' => $this->producer->id,
            'genre_voulu' => MissionGender::Tous,
            'type_mission' => MissionType::Publicite,
            'budget' => 50000,
            'date_limite_candidature' => now()->addDays(10)->toDateString(),
            'date_tournage' => now()->addDays(20)->toDateString(),
        ]);
    }

    private function makeUgcMission(): Mission
    {
        return $this->makeMission([
            'type_mission' => MissionType::Ugc,
            'type_compensation' => 'product',
            'nom_produit' => 'Crème',
            'budget' => 0,
            'date_tournage' => null,
            'lieu' => null,
        ]);
    }

    private function notify(Mission $mission): void
    {
        app(NotifyFacesOfNewMission::class)->handle(new MissionPublished($mission));
    }

    /**
     * @return list<int>
     */
    private function pushedUserIds(): array
    {
        return Bus::dispatched(SendWebPush::class)->map(fn (SendWebPush $j) => $j->userId)->values()->all();
    }

    private function assertMailQueuedTo(User $user): void
    {
        Mail::assertQueued(NewMissionAvailableMail::class, fn (NewMissionAvailableMail $m) => $m->hasTo($user->email));
    }

    private function assertMailNotQueuedTo(User $user): void
    {
        Mail::assertNotQueued(NewMissionAvailableMail::class, fn (NewMissionAvailableMail $m) => $m->hasTo($user->email));
    }

    // --- Push -------------------------------------------------------------------

    public function test_push_goes_to_free_and_paid_faces_with_a_subscription_but_not_to_the_producer(): void
    {
        [, $free] = $this->makeFace();
        [, $paid] = $this->makeFace(FaceSubscriptionPlan::Starter);
        [, $noDevice] = $this->makeFace(push: false);
        $mission = $this->makeMission(['titre' => 'Pub lessive', 'lieu' => 'Cotonou']);

        $this->notify($mission);

        $ids = $this->pushedUserIds();
        $this->assertEqualsCanonicalizing([$free->id, $paid->id], $ids);
        $this->assertNotContains($noDevice->id, $ids);
        $this->assertNotContains($this->producerUser->id, $ids);

        Bus::assertDispatched(SendWebPush::class, function (SendWebPush $job) use ($mission): bool {
            return $job->payload['title'] === 'Nouvelle mission : Pub lessive'
                && str_contains($job->payload['body'], 'Cotonou')
                && str_contains($job->payload['body'], 'FCFA')
                && $job->payload['url'] === "/face/missions/{$mission->uuid}"
                && $job->payload['tag'] === "new-mission-{$mission->uuid}"
                && $job->payload['ttl'] === 86400;
        });
    }

    public function test_gender_filter_skips_non_matching_faces_but_notifies_unknown_gender(): void
    {
        [, $woman] = $this->makeFace(faceAttrs: ['sexe' => 'femme']);
        [, $man] = $this->makeFace(faceAttrs: ['sexe' => 'homme']);
        [, $unknown] = $this->makeFace(faceAttrs: ['sexe' => null]);

        $this->notify($this->makeMission(['genre_voulu' => MissionGender::Homme]));

        $this->assertEqualsCanonicalizing([$man->id, $unknown->id], $this->pushedUserIds());
        $this->assertNotContains($woman->id, $this->pushedUserIds());
    }

    public function test_mission_for_everyone_notifies_both_genders(): void
    {
        [, $woman] = $this->makeFace(faceAttrs: ['sexe' => 'femme']);
        [, $man] = $this->makeFace(faceAttrs: ['sexe' => 'homme']);

        $this->notify($this->makeMission(['genre_voulu' => MissionGender::Tous]));

        $this->assertEqualsCanonicalizing([$woman->id, $man->id], $this->pushedUserIds());
    }

    public function test_inactive_user_is_not_notified(): void
    {
        [, $inactive] = $this->makeFace(plan: FaceSubscriptionPlan::Pro, userAttrs: ['is_active' => false]);
        [, $active] = $this->makeFace();

        $this->notify($this->makeMission());

        $this->assertSame([$active->id], $this->pushedUserIds());
        $this->assertMailNotQueuedTo($inactive);
    }

    // --- E-mail -----------------------------------------------------------------

    public function test_standard_mission_email_goes_to_paid_tiers_only(): void
    {
        [, $free] = $this->makeFace();
        [, $starter] = $this->makeFace(FaceSubscriptionPlan::Starter);
        [, $pro] = $this->makeFace(FaceSubscriptionPlan::Pro);
        [, $elite] = $this->makeFace(FaceSubscriptionPlan::Elite);

        $this->notify($this->makeMission());

        $this->assertMailQueuedTo($starter);
        $this->assertMailQueuedTo($pro);
        $this->assertMailQueuedTo($elite);
        $this->assertMailNotQueuedTo($free);
        $this->assertMailNotQueuedTo($this->producerUser);
        Mail::assertQueuedCount(3);
    }

    public function test_expired_subscription_gets_no_email(): void
    {
        $face = Face::factory()->create();
        $user = User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);
        FaceSubscription::factory()->expired()->create(['face_id' => $face->id, 'plan' => FaceSubscriptionPlan::Pro]);

        $this->notify($this->makeMission());

        $this->assertMailNotQueuedTo($user);
    }

    public function test_ugc_mission_email_goes_to_pro_and_elite_only_and_not_to_suspended_faces(): void
    {
        [, $starter] = $this->makeFace(FaceSubscriptionPlan::Starter);
        [, $pro] = $this->makeFace(FaceSubscriptionPlan::Pro);
        [, $elite] = $this->makeFace(FaceSubscriptionPlan::Elite);
        [$suspendedFace, $suspended] = $this->makeFace(FaceSubscriptionPlan::Pro);
        UgcSuspension::create([
            'face_id' => $suspendedFace->id,
            'shipment_id' => null,
            'reason' => UgcSuspensionReason::UnboxingDeadlineMissed,
            'appeal_status' => UgcSuspensionAppealStatus::None,
            'suspended_at' => now(),
        ]);

        $this->notify($this->makeUgcMission());

        $this->assertMailQueuedTo($pro);
        $this->assertMailQueuedTo($elite);
        $this->assertMailNotQueuedTo($starter);
        $this->assertMailNotQueuedTo($suspended);
        Mail::assertQueuedCount(2);
    }

    public function test_unverified_email_gets_no_email(): void
    {
        [, $unverified] = $this->makeFace(FaceSubscriptionPlan::Pro, userAttrs: ['email_verified_at' => null]);

        $this->notify($this->makeMission());

        $this->assertMailNotQueuedTo($unverified);
    }

    public function test_email_content_mentions_plan_and_links_to_the_mission(): void
    {
        [, $pro] = $this->makeFace(FaceSubscriptionPlan::Pro);
        $mission = $this->makeMission(['titre' => 'Pub lessive']);

        $this->notify($mission);

        Mail::assertQueued(NewMissionAvailableMail::class, function (NewMissionAvailableMail $mail) use ($mission): bool {
            $html = $mail->render();

            return $mail->envelope()->subject === 'En avant-première : Pub lessive'
                && str_contains($html, "/face/missions/{$mission->uuid}")
                && str_contains($html, 'Voir la mission')
                && str_contains($html, 'en avant-première grâce à votre abonnement Pro');
        });
    }

    // --- Daily cap ----------------------------------------------------------------

    public function test_daily_cap_blocks_the_fourth_push_and_mail_and_resets_next_business_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-16 09:00:00', 'Africa/Porto-Novo'));
        [, $user] = $this->makeFace(FaceSubscriptionPlan::Pro);

        for ($i = 0; $i < 4; $i++) {
            $this->notify($this->makeMission());
        }

        $this->assertSame(3, count(array_keys($this->pushedUserIds(), $user->id)));
        Mail::assertQueuedCount(3);

        Carbon::setTestNow(Carbon::parse('2026-10-17 09:00:00', 'Africa/Porto-Novo'));
        $this->notify($this->makeMission());

        $this->assertSame(4, count(array_keys($this->pushedUserIds(), $user->id)));
        Mail::assertQueuedCount(4);

        Carbon::setTestNow();
    }

    // --- Run-time guards ----------------------------------------------------------

    public function test_expired_mission_at_job_time_sends_nothing(): void
    {
        $this->makeFace(FaceSubscriptionPlan::Pro);
        $mission = $this->makeMission();
        $mission->forceFill(['date_limite_candidature' => now()->subDay()->toDateString()])->saveQuietly();

        $this->notify($mission);

        Bus::assertNotDispatched(SendWebPush::class);
        Mail::assertNothingQueued();
    }

    public function test_closed_mission_at_job_time_sends_nothing(): void
    {
        $this->makeFace(FaceSubscriptionPlan::Pro);
        $mission = $this->makeMission();
        $mission->update(['status' => MissionStatus::Closed]);

        $this->notify($mission);

        Bus::assertNotDispatched(SendWebPush::class);
        Mail::assertNothingQueued();
    }

    public function test_inactive_producer_at_job_time_sends_nothing(): void
    {
        $this->makeFace(FaceSubscriptionPlan::Pro);
        $mission = $this->makeMission();
        $this->producerUser->update(['is_active' => false]);

        $this->notify($mission);

        Bus::assertNotDispatched(SendWebPush::class);
        Mail::assertNothingQueued();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Cache::flush();

        parent::tearDown();
    }
}
