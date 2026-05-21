<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\FaceSubscriptionStatus;
use App\Models\Face;
use App\Models\FacePhoto;
use App\Models\FaceSubscription;
use App\Models\FaceVideo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditFacePremiumReadinessCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_outputs_zero_counts_on_empty_database(): void
    {
        $this->artisan('faces:audit-premium-readiness')
            ->expectsOutputToContain('=== Face Premium readiness audit ===')
            ->expectsOutputToContain('Active premium subscriptions: 0')
            ->expectsOutputToContain('Distinct Faces with active premium: 0')
            ->expectsOutputToContain('Free Faces with > 2 album photos (positions 3-4 will be locked at launch): 0')
            ->expectsOutputToContain('Free Faces with an acting video (will be hidden publicly at launch): 0')
            ->expectsOutputToContain('Active subscriptions with NULL expires_at: 0')
            ->expectsOutputToContain('Active subscriptions with past expires_at (stale, awaiting expiry cron): 0')
            ->expectsOutputToContain('Audit complete.')
            ->assertExitCode(0);
    }

    public function test_command_counts_active_premium_subscriptions_and_distinct_faces(): void
    {
        // 2 fresh premium faces
        $faceA = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $faceA->id]);
        FaceSubscription::factory()->active()->create(['face_id' => $faceA->id]);

        $faceB = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $faceB->id]);
        FaceSubscription::factory()->active()->create(['face_id' => $faceB->id]);

        // 1 face with chained renewal (1 expired + 1 active)
        $faceC = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $faceC->id]);
        FaceSubscription::factory()->active()->create([
            'face_id' => $faceC->id,
            'starts_at' => now()->subYear()->subDay(),
            'expires_at' => now()->subDay(),
            'status' => FaceSubscriptionStatus::Expired,
        ]);
        FaceSubscription::factory()->active()->create([
            'face_id' => $faceC->id,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addYear(),
        ]);

        // 1 face with stale Active (expires_at in past)
        $faceD = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $faceD->id]);
        FaceSubscription::factory()->active()->create([
            'face_id' => $faceD->id,
            'starts_at' => now()->subYear()->subDay(),
            'expires_at' => now()->subHour(),
        ]);

        $this->artisan('faces:audit-premium-readiness')
            ->expectsOutputToContain('Active premium subscriptions: 3')
            ->expectsOutputToContain('Distinct Faces with active premium: 3')
            ->expectsOutputToContain('Active subscriptions with NULL expires_at: 0')
            ->expectsOutputToContain('Active subscriptions with past expires_at (stale, awaiting expiry cron): 1')
            ->assertExitCode(0);
    }

    public function test_command_counts_free_faces_with_more_than_two_album_photos(): void
    {
        // 2 free Faces with 4 photos each
        $freeFour1 = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $freeFour1->id]);
        FacePhoto::factory()->createSequentialForFace($freeFour1, 4);

        $freeFour2 = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $freeFour2->id]);
        FacePhoto::factory()->createSequentialForFace($freeFour2, 4);

        // 1 free Face with 2 photos (boundary — NOT counted because > 2 is strict)
        $freeTwo = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $freeTwo->id]);
        FacePhoto::factory()->createSequentialForFace($freeTwo, 2);

        // 1 free Face with 3 photos (just over — IS counted)
        $freeThree = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $freeThree->id]);
        FacePhoto::factory()->createSequentialForFace($freeThree, 3);

        // 1 free Face with 0 photos (NOT counted)
        $freeZero = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $freeZero->id]);

        // 1 active-premium Face with 4 photos (NOT counted because premium Faces are excluded)
        $premiumFour = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $premiumFour->id]);
        FacePhoto::factory()->createSequentialForFace($premiumFour, 4);
        FaceSubscription::factory()->active()->create(['face_id' => $premiumFour->id]);

        $this->artisan('faces:audit-premium-readiness')
            ->expectsOutputToContain('Free Faces with > 2 album photos (positions 3-4 will be locked at launch): 3')
            ->assertExitCode(0);
    }

    public function test_command_counts_free_faces_with_non_null_acting_video(): void
    {
        // 2 free Faces with an acting video
        $freeA = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $freeA->id]);
        FaceVideo::factory()->acting()->create(['face_id' => $freeA->id]);

        $freeB = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $freeB->id]);
        FaceVideo::factory()->acting()->create(['face_id' => $freeB->id]);

        // 1 free Face with no acting video
        $freeNone = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $freeNone->id]);

        // 1 active-premium Face with an acting video (NOT counted because premium)
        $premium = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $premium->id]);
        FaceVideo::factory()->acting()->create(['face_id' => $premium->id]);
        FaceSubscription::factory()->active()->create(['face_id' => $premium->id]);

        $this->artisan('faces:audit-premium-readiness')
            ->expectsOutputToContain('Free Faces with an acting video (will be hidden publicly at launch): 2')
            ->assertExitCode(0);
    }

    public function test_command_counts_active_subscriptions_with_null_expires_at(): void
    {
        $face = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
            'expires_at' => null,
        ]);

        $this->artisan('faces:audit-premium-readiness')
            ->expectsOutputToContain('Active premium subscriptions: 0')
            ->expectsOutputToContain('Distinct Faces with active premium: 0')
            ->expectsOutputToContain('Active subscriptions with NULL expires_at: 1')
            ->assertExitCode(0);
    }

    public function test_command_emits_per_face_lines_only_with_detailed_flag(): void
    {
        // Fixture identical to test_command_counts_free_faces_with_more_than_two_album_photos
        // so that Section B has 3 free Faces with > 2 album photos to enumerate.
        $freeFour1 = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $freeFour1->id]);
        FacePhoto::factory()->createSequentialForFace($freeFour1, 4);

        $freeFour2 = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $freeFour2->id]);
        FacePhoto::factory()->createSequentialForFace($freeFour2, 4);

        $freeThree = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $freeThree->id]);
        FacePhoto::factory()->createSequentialForFace($freeThree, 3);

        $freeWithActingVideo = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $freeWithActingVideo->id]);
        FaceVideo::factory()->acting()->create(['face_id' => $freeWithActingVideo->id]);

        $this->assertSame(0, Artisan::call('faces:audit-premium-readiness'));
        $output = Artisan::output();
        $this->assertStringContainsString('Free Faces with > 2 album photos', $output);
        $this->assertStringNotContainsString('  - face#', $output);
        $this->assertStringNotContainsString('acting_videos=', $output);

        $this->assertSame(0, Artisan::call('faces:audit-premium-readiness', ['--detailed' => true]));
        $detailedOutput = Artisan::output();
        $this->assertStringContainsString('Free Faces with > 2 album photos', $detailedOutput);
        $this->assertStringContainsString('  - face#', $detailedOutput);
        $this->assertStringContainsString('acting_videos=1', $detailedOutput);
    }

    public function test_command_performs_no_writes(): void
    {
        // 1 free Face
        $freeFace = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $freeFace->id]);
        FacePhoto::factory()->createSequentialForFace($freeFace, 4);
        FaceVideo::factory()->acting()->create(['face_id' => $freeFace->id]);

        // 1 active-premium Face
        $premiumFace = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $premiumFace->id]);
        FacePhoto::factory()->createSequentialForFace($premiumFace, 4);
        FaceVideo::factory()->acting()->create(['face_id' => $premiumFace->id]);
        FaceSubscription::factory()->active()->create(['face_id' => $premiumFace->id]);

        // 1 stale-Active Face (Active row with past expires_at)
        $staleFace = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $staleFace->id]);
        FaceVideo::factory()->acting()->create(['face_id' => $staleFace->id]);
        FaceSubscription::factory()->active()->create([
            'face_id' => $staleFace->id,
            'expires_at' => now()->subHour(),
        ]);

        $beforeSubscriptions = FaceSubscription::query()->orderBy('id')->get()->toArray();
        $beforeFaces = Face::query()->orderBy('id')->get(['id', 'is_featured'])->toArray();
        $beforePhotos = FacePhoto::query()->orderBy('id')->get()->toArray();
        $beforeVideos = FaceVideo::query()->orderBy('id')->get()->toArray();
        $beforeAuditRows = DB::table('face_subscription_audits')->orderBy('id')->get()->toArray();

        $this->artisan('faces:audit-premium-readiness', ['--detailed' => true])
            ->assertExitCode(0);

        $this->assertEquals($beforeSubscriptions, FaceSubscription::query()->orderBy('id')->get()->toArray());
        $this->assertEquals($beforeFaces, Face::query()->orderBy('id')->get(['id', 'is_featured'])->toArray());
        $this->assertEquals($beforePhotos, FacePhoto::query()->orderBy('id')->get()->toArray());
        $this->assertEquals($beforeVideos, FaceVideo::query()->orderBy('id')->get()->toArray());
        $this->assertEquals($beforeAuditRows, DB::table('face_subscription_audits')->orderBy('id')->get()->toArray());
    }
}
