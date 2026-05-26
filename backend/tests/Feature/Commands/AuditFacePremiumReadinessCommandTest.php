<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

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
        $this->assertSame(0, Artisan::call('faces:audit-premium-readiness'));
        $output = Artisan::output();

        // Round 3 M7: section-anchored assertions so a regression that drops Section A
        // (or Section E) silently cannot be hidden by the other section emitting the same substring.
        $this->assertStringContainsString('=== Face Premium readiness audit (FP-2 tier model) ===', $output);
        $this->assertStringContainsString('Active subscriptions by tier:', $output);
        $this->assertStringContainsString('Starter: 0', $output);
        $this->assertStringContainsString('Pro:     0', $output);
        $this->assertStringContainsString('Élite:   0', $output);
        $this->assertStringContainsString('Distinct Faces with active paid subscription: 0', $output);
        $this->assertStringContainsString('Free Faces with > 1 album photo (positions 2+ will be hidden publicly at launch): 0', $output);
        $this->assertStringContainsString('Free Faces with a presentation video (hidden publicly at launch): 0', $output);
        $this->assertStringContainsString('Free Faces with an acting video    (hidden publicly at launch): 0', $output);
        $this->assertStringContainsString('Free Faces with a UGC video        (hidden publicly at launch): 0', $output);
        $this->assertStringContainsString('Active subscriptions with NULL expires_at: 0', $output);
        $this->assertStringContainsString('Active subscriptions with past expires_at (stale, awaiting expiry cron): 0', $output);
        $this->assertStringContainsString('Effective tier distribution across all Faces:', $output);
        // Section A Total (no `Faces` suffix) and Section E Total (`Faces` suffix) are anchored separately:
        $this->assertMatchesRegularExpression('/Total:\s+0\R/', $output, 'Section A must render "Total:   0" (no trailing "Faces").');
        $this->assertStringContainsString('Total:   0 Faces', $output);
        $this->assertStringContainsString('Audit complete.', $output);
    }

    public function test_command_counts_active_subscriptions_per_tier_and_distinct_faces(): void
    {
        $faceStarter = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $faceStarter->id]);
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $faceStarter->id]);

        $faceProA = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $faceProA->id]);
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $faceProA->id]);

        $faceProB = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $faceProB->id]);
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $faceProB->id]);

        $faceElite = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $faceElite->id]);
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $faceElite->id]);

        // Chained renewal: Expired + Active on the same Face
        $faceRenewed = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $faceRenewed->id]);
        FaceSubscription::factory()->pro()->expired()->create(['face_id' => $faceRenewed->id]);
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $faceRenewed->id]);

        // Stale Active (past expires_at) — surfaces in Section D, NOT Section A
        $faceStale = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $faceStale->id]);
        FaceSubscription::factory()->pro()->active()->create([
            'face_id' => $faceStale->id,
            'expires_at' => now()->subHour(),
        ]);

        $this->artisan('faces:audit-premium-readiness')
            ->expectsOutputToContain('Active subscriptions by tier:')
            ->expectsOutputToContain('Starter: 1')
            ->expectsOutputToContain('Pro:     3')
            ->expectsOutputToContain('Élite:   1')
            ->expectsOutputToContain('Total:   5')
            ->expectsOutputToContain('Distinct Faces with active paid subscription: 5')
            ->expectsOutputToContain('Active subscriptions with NULL expires_at: 0')
            ->expectsOutputToContain('Active subscriptions with past expires_at (stale, awaiting expiry cron): 1')
            ->assertExitCode(0);
    }

    public function test_command_counts_free_faces_with_more_than_one_album_photo(): void
    {
        // 2 free Faces with 4 photos each — counted
        $freeFour1 = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $freeFour1->id]);
        FacePhoto::factory()->createSequentialForFace($freeFour1, 4);

        $freeFour2 = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $freeFour2->id]);
        FacePhoto::factory()->createSequentialForFace($freeFour2, 4);

        // 1 free Face with 2 photos — counted (> 1 threshold)
        $freeTwo = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $freeTwo->id]);
        FacePhoto::factory()->createSequentialForFace($freeTwo, 2);

        // 1 free Face with 1 photo — NOT counted (boundary)
        $freeOne = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $freeOne->id]);
        FacePhoto::factory()->createSequentialForFace($freeOne, 1);

        // 1 free Face with 0 photos — NOT counted
        $freeZero = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $freeZero->id]);

        // 1 active-premium Face with 4 photos — NOT counted (not free)
        $premiumFour = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $premiumFour->id]);
        FacePhoto::factory()->createSequentialForFace($premiumFour, 4);
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $premiumFour->id]);

        $this->artisan('faces:audit-premium-readiness')
            ->expectsOutputToContain('Free Faces with > 1 album photo (positions 2+ will be hidden publicly at launch): 3')
            ->assertExitCode(0);
    }

    public function test_command_counts_free_faces_with_each_video_type(): void
    {
        // 1 free Face with a presentation video
        $facePresentation = Face::factory()->create(['presentation_video' => 'p.mp4']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $facePresentation->id]);

        // 2 free Faces with an acting video
        $faceActing1 = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $faceActing1->id]);
        FaceVideo::factory()->acting()->create(['face_id' => $faceActing1->id]);

        $faceActing2 = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $faceActing2->id]);
        FaceVideo::factory()->acting()->create(['face_id' => $faceActing2->id]);

        // 1 free Face with a UGC video
        $faceUgc = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $faceUgc->id]);
        FaceVideo::factory()->ugc()->create(['face_id' => $faceUgc->id]);

        // 1 active-premium Face with all three media surfaces — NOT counted (not free)
        $facePaidAll = Face::factory()->create(['presentation_video' => 'paid-p.mp4']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $facePaidAll->id]);
        FaceVideo::factory()->acting()->create(['face_id' => $facePaidAll->id]);
        FaceVideo::factory()->ugc()->create(['face_id' => $facePaidAll->id]);
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $facePaidAll->id]);

        $this->artisan('faces:audit-premium-readiness')
            ->expectsOutputToContain('Free Faces with a presentation video (hidden publicly at launch): 1')
            ->expectsOutputToContain('Free Faces with an acting video    (hidden publicly at launch): 2')
            ->expectsOutputToContain('Free Faces with a UGC video        (hidden publicly at launch): 1')
            ->assertExitCode(0);
    }

    public function test_command_counts_active_subscriptions_with_null_expires_at(): void
    {
        $face = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        FaceSubscription::factory()->pro()->active()->create([
            'face_id' => $face->id,
            'expires_at' => null,
        ]);

        $this->artisan('faces:audit-premium-readiness')
            ->expectsOutputToContain('Pro:     0')
            ->expectsOutputToContain('Total:   0')
            ->expectsOutputToContain('Distinct Faces with active paid subscription: 0')
            ->expectsOutputToContain('Active subscriptions with NULL expires_at: 1')
            ->assertExitCode(0);
    }

    public function test_command_emits_per_face_lines_only_with_detailed_flag(): void
    {
        // Section B fixture: 2 free Faces with 4 photos + 1 free Face with 2 photos = 3 over-quota
        $freeFour1 = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $freeFour1->id]);
        FacePhoto::factory()->createSequentialForFace($freeFour1, 4);

        $freeFour2 = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $freeFour2->id]);
        FacePhoto::factory()->createSequentialForFace($freeFour2, 4);

        $freeTwo = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $freeTwo->id]);
        FacePhoto::factory()->createSequentialForFace($freeTwo, 2);

        // Section C fixtures: 1 free Face with presentation + 1 free Face with acting video
        $freeWithPresentationVideo = Face::factory()->create(['presentation_video' => 'detailed-p.mp4']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $freeWithPresentationVideo->id]);

        $freeWithActingVideo = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $freeWithActingVideo->id]);
        FaceVideo::factory()->acting()->create(['face_id' => $freeWithActingVideo->id]);

        // Without --detailed: section headings + counts but NO per-face lines
        $this->assertSame(0, Artisan::call('faces:audit-premium-readiness'));
        $output = Artisan::output();
        $this->assertStringContainsString('Free Faces with > 1 album photo', $output);
        $this->assertStringContainsString('Free Faces with a presentation video', $output);
        $this->assertStringNotContainsString('  - face#', $output);
        $this->assertStringNotContainsString('has_presentation_video=', $output);
        $this->assertStringNotContainsString('acting_videos=', $output);

        // With --detailed: per-face enumeration in Sections B + C
        $this->assertSame(0, Artisan::call('faces:audit-premium-readiness', ['--detailed' => true]));
        $detailedOutput = Artisan::output();
        $this->assertStringContainsString('Free Faces with > 1 album photo', $detailedOutput);
        $this->assertStringContainsString('  - face#', $detailedOutput);
        $this->assertStringContainsString('has_presentation_video=1', $detailedOutput);
        $this->assertStringContainsString('acting_videos=1', $detailedOutput);
    }

    public function test_command_renders_effective_tier_distribution(): void
    {
        // 1 free Face (no row)
        $freeFace = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $freeFace->id]);

        // 1 Starter active
        $starterFace = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $starterFace->id]);
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $starterFace->id]);

        // 2 Pro active
        $proFaceA = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $proFaceA->id]);
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $proFaceA->id]);

        $proFaceB = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $proFaceB->id]);
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $proFaceB->id]);

        // 1 Elite active
        $eliteFace = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $eliteFace->id]);
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $eliteFace->id]);

        $this->artisan('faces:audit-premium-readiness')
            ->expectsOutputToContain('Effective tier distribution across all Faces:')
            ->expectsOutputToContain('Free:    1 (20%)')
            ->expectsOutputToContain('Starter: 1 (20%)')
            ->expectsOutputToContain('Pro:     2 (40%)')
            ->expectsOutputToContain('Élite:   1 (20%)')
            ->expectsOutputToContain('Total:   5 Faces')
            ->assertExitCode(0);
    }

    public function test_command_renders_zero_percent_on_empty_database_without_division_by_zero(): void
    {
        $this->assertSame(0, Artisan::call('faces:audit-premium-readiness'));
        $output = Artisan::output();

        $this->assertStringContainsString('Free:    0 (0%)', $output);
        $this->assertStringContainsString('Starter: 0 (0%)', $output);
        $this->assertStringContainsString('Pro:     0 (0%)', $output);
        $this->assertStringContainsString('Élite:   0 (0%)', $output);
        $this->assertStringContainsString('Total:   0 Faces', $output);
    }

    public function test_section_e_routes_multi_active_face_to_highest_expires_at_tier(): void
    {
        // A Face with 2 concurrent Active rows of different plans (webhook double-fire scenario).
        // D1: Section E must pick the canonical row (highest expires_at, id tie-break), like Face::activeSubscription,
        // so the Face contributes to exactly one tier bucket — NOT both.
        $face = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        FaceSubscription::factory()->starter()->active()->create([
            'face_id' => $face->id,
            'expires_at' => now()->addDays(10),
        ]);
        FaceSubscription::factory()->elite()->active()->create([
            'face_id' => $face->id,
            'expires_at' => now()->addDays(30),
        ]);

        $this->assertSame(0, Artisan::call('faces:audit-premium-readiness'));
        $output = Artisan::output();

        // Round 3 M7: anchor Section A's "Élite: 1" via the surrounding "Total: 2" so the assertion
        // doesn't pass on Section E's "Élite: 1 (100%)" when Section A would be regressed away.
        $this->assertStringContainsString('Starter: 1', $output);
        $this->assertMatchesRegularExpression('/Élite:\s+1\R\s+Total:\s+2/u', $output, 'Section A must render Élite=1 immediately before Total=2.');
        $this->assertStringContainsString('Distinct Faces with active paid subscription: 1', $output);
        $this->assertStringContainsString('Effective tier distribution across all Faces:', $output);
        // Section E zero-counts (with % suffix unique to Section E):
        $this->assertStringContainsString('Free:    0 (0%)', $output);
        $this->assertStringContainsString('Starter: 0 (0%)', $output);
        $this->assertStringContainsString('Élite:   1 (100%)', $output);
        $this->assertStringContainsString('Total:   1 Faces', $output);
    }

    public function test_section_a_and_e_surface_unknown_plans_with_dedicated_bucket(): void
    {
        // 2 known-tier Faces + 1 Face holding a legacy/unknown plan row.
        $proFace = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $proFace->id]);
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $proFace->id]);

        $eliteFace = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $eliteFace->id]);
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $eliteFace->id]);

        // Legacy FP-1 row (or a future plan not yet wired into the CASE) — bypass the enum via raw insert.
        $legacyFace = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $legacyFace->id]);
        DB::table('face_subscriptions')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'face_id' => $legacyFace->id,
            'plan' => 'annual_premium',
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
            'paid_amount' => null,
            'currency' => 'XOF',
            'provider' => 'manual',
            'provider_reference' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(0, Artisan::call('faces:audit-premium-readiness', ['--detailed' => true]));
        $output = Artisan::output();

        $this->assertStringContainsString('Pro:     1', $output);
        $this->assertStringContainsString('Élite:   1', $output);
        // Section A Unknown line (with the plan list suffix unique to Section A):
        $this->assertStringContainsString('Unknown: 1 (plans: annual_premium)', $output);
        $this->assertStringContainsString('Total:   3', $output);
        $this->assertStringContainsString('Effective tier distribution across all Faces:', $output);
        // Section E Unknown line (Round 3 M7: anchor on the "see Section A enumeration above" suffix
        // which is emitted only by Section E, so this assertion cannot pass on Section A's line alone):
        $this->assertStringContainsString('see Section A enumeration above', $output);
        // Section A --detailed enumeration line (anchored by the explicit "plan=annual_premium" key):
        $this->assertMatchesRegularExpression('/  - subscription#\d+ face#\d+ plan=annual_premium/u', $output);
    }

    public function test_section_e_percentages_sum_to_exactly_100_with_residual_rounding(): void
    {
        // 8 Faces split 1/1/3/3 — naive per-bucket rounding yields 13/13/38/38 = 102%.
        // D4: residual-into-largest must produce a sum that is exactly 100% in rendered output.
        $freeFace = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $freeFace->id]);

        $starterFace = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $starterFace->id]);
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $starterFace->id]);

        for ($i = 0; $i < 3; $i++) {
            $proFace = Face::factory()->create();
            User::factory()->create(['userable_type' => Face::class, 'userable_id' => $proFace->id]);
            FaceSubscription::factory()->pro()->active()->create(['face_id' => $proFace->id]);
        }

        for ($i = 0; $i < 3; $i++) {
            $eliteFace = Face::factory()->create();
            User::factory()->create(['userable_type' => Face::class, 'userable_id' => $eliteFace->id]);
            FaceSubscription::factory()->elite()->active()->create(['face_id' => $eliteFace->id]);
        }

        $this->assertSame(0, Artisan::call('faces:audit-premium-readiness'));
        $output = Artisan::output();

        // Pro is the first key reaching the max count (tied with Élite at 3) → gets the +2 residual.
        $this->assertStringContainsString('Free:    1 (12%)', $output);
        $this->assertStringContainsString('Starter: 1 (12%)', $output);
        $this->assertStringContainsString('Pro:     3 (39%)', $output);
        $this->assertStringContainsString('Élite:   3 (37%)', $output);
        $this->assertStringContainsString('Total:   8 Faces', $output);

        // Mechanical pin: parse all rendered percents in the tier distribution block and assert they sum to 100.
        $sum = 0;
        foreach (['Free', 'Starter', 'Pro', 'Élite'] as $label) {
            $pattern = '/'.preg_quote($label, '/').':\s+\d+ \((\d+)%\)/u';
            $matches = [];
            $this->assertSame(1, preg_match($pattern, $output, $matches), "Missing percent for {$label}");
            $sum += (int) $matches[1];
        }
        $this->assertSame(100, $sum, 'Section E percentages must sum to exactly 100%.');
    }

    public function test_section_c_counts_overlap_when_a_single_free_face_holds_all_three_video_surfaces(): void
    {
        // C6: one Free Face with presentation + acting + UGC. The 3 lines overlap — the same Face
        // is counted in all 3 rows. The audit output prints an overlap note so operators don't double-read.
        $face = Face::factory()->create(['presentation_video' => 'all-surfaces.mp4']);
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);
        FaceVideo::factory()->acting()->create(['face_id' => $face->id]);
        FaceVideo::factory()->ugc()->create(['face_id' => $face->id]);

        $this->artisan('faces:audit-premium-readiness')
            ->expectsOutputToContain('Free Faces with a presentation video (hidden publicly at launch): 1')
            ->expectsOutputToContain('Free Faces with an acting video    (hidden publicly at launch): 1')
            ->expectsOutputToContain('Free Faces with a UGC video        (hidden publicly at launch): 1')
            ->expectsOutputToContain('Note: the 3 lines overlap')
            ->assertExitCode(0);
    }

    public function test_command_performs_no_writes(): void
    {
        // 1 Pro Face with photos + acting video
        $proFace = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $proFace->id]);
        FacePhoto::factory()->createSequentialForFace($proFace, 4);
        FaceVideo::factory()->acting()->create(['face_id' => $proFace->id]);
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $proFace->id]);

        // 1 Elite Face with photos + UGC video
        $eliteFace = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $eliteFace->id]);
        FacePhoto::factory()->createSequentialForFace($eliteFace, 6);
        FaceVideo::factory()->ugc()->create(['face_id' => $eliteFace->id]);
        FaceSubscription::factory()->elite()->active()->create(['face_id' => $eliteFace->id]);

        // 1 free Face with 4 photos + acting video
        $freeFace = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $freeFace->id]);
        FacePhoto::factory()->createSequentialForFace($freeFace, 4);
        FaceVideo::factory()->acting()->create(['face_id' => $freeFace->id]);

        // 1 stale Active (past expires_at)
        $staleFace = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $staleFace->id]);
        FaceSubscription::factory()->pro()->active()->create([
            'face_id' => $staleFace->id,
            'expires_at' => now()->subHour(),
        ]);

        $beforeSubscriptions = FaceSubscription::query()->orderBy('id')->get()->toArray();
        $beforeFaces = Face::query()->orderBy('id')->get(['id', 'is_featured', 'presentation_video'])->toArray();
        $beforePhotos = FacePhoto::query()->orderBy('id')->get()->toArray();
        $beforeVideos = FaceVideo::query()->orderBy('id')->get()->toArray();
        $beforeAuditRows = DB::table('face_subscription_audits')->orderBy('id')->get()->toArray();

        $this->artisan('faces:audit-premium-readiness', ['--detailed' => true])
            ->assertExitCode(0);

        $this->assertEquals($beforeSubscriptions, FaceSubscription::query()->orderBy('id')->get()->toArray());
        $this->assertEquals($beforeFaces, Face::query()->orderBy('id')->get(['id', 'is_featured', 'presentation_video'])->toArray());
        $this->assertEquals($beforePhotos, FacePhoto::query()->orderBy('id')->get()->toArray());
        $this->assertEquals($beforeVideos, FaceVideo::query()->orderBy('id')->get()->toArray());
        $this->assertEquals($beforeAuditRows, DB::table('face_subscription_audits')->orderBy('id')->get()->toArray());
    }
}
