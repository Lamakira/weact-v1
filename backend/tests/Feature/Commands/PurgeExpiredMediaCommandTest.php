<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\Face;
use App\Models\FacePhoto;
use App\Models\FaceSubscription;
use App\Models\FaceSubscriptionAudit;
use App\Models\FaceVideo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurgeExpiredMediaCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function makeFaceWithUser(array $attributes = []): Face
    {
        $face = Face::factory()->create($attributes);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        return $face;
    }

    private function seedPhotoFilesOnDisk(FacePhoto $photo): void
    {
        Storage::disk('public')->put('avatars/faces/albums/'.$photo->filename, 'photo bytes');
        if ($photo->thumbnail) {
            Storage::disk('public')->put('avatars/faces/albums/thumbnails/'.$photo->thumbnail, 'thumb bytes');
        }
        if ($photo->medium) {
            Storage::disk('public')->put('avatars/faces/albums/medium/'.$photo->medium, 'medium bytes');
        }
        if ($photo->grid) {
            Storage::disk('public')->put('avatars/faces/albums/grid/'.$photo->grid, 'grid bytes');
        }
        if ($photo->large) {
            Storage::disk('public')->put('avatars/faces/albums/large/'.$photo->large, 'large bytes');
        }
    }

    private function seedVideoFilesOnDisk(FaceVideo $video): void
    {
        $type = $video->type->value;
        Storage::disk('public')->put("videos/faces/{$type}/".$video->filename, 'video');
        if ($video->thumbnail) {
            Storage::disk('public')->put("videos/faces/{$type}/thumbnails/".$video->thumbnail, 'thumb');
        }
    }

    public function test_command_outputs_summary_when_no_faces_have_terminated_subscriptions(): void
    {
        $this->artisan('faces:purge-expired-media')
            ->expectsOutputToContain('Found 0 Face(s) with at least one paid termination event.')
            ->expectsOutputToContain('Done. Faces purged: 0, Photos: 0, Acting videos: 0, UGC videos: 0, Errored: 0.')
            ->assertExitCode(0);
    }

    public function test_command_does_not_purge_within_retention_window_for_expired_pro_to_free_transition(): void
    {
        $face = $this->makeFaceWithUser(['prenom' => 'Pro Expired Recently']);

        $photos = FacePhoto::factory()->createSequentialForFace($face, 4);
        foreach ($photos as $photo) {
            $this->seedPhotoFilesOnDisk($photo);
        }

        FaceSubscription::factory()->pro()->expired()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subYear()->subDays(30),
            'expires_at' => now()->subDays(30),
        ]);

        $this->artisan('faces:purge-expired-media')
            ->expectsOutputToContain('Found 1 Face(s)')
            ->expectsOutputToContain('Done. Faces purged: 0, Photos: 0, Acting videos: 0, UGC videos: 0, Errored: 0.')
            ->assertExitCode(0);

        $this->assertSame(4, FacePhoto::where('face_id', $face->id)->count());
        foreach ($photos as $photo) {
            Storage::disk('public')->assertExists('avatars/faces/albums/'.$photo->filename);
            Storage::disk('public')->assertExists('avatars/faces/albums/thumbnails/'.$photo->thumbnail);
        }
    }

    public function test_command_purges_over_quota_photos_after_retention_window_elapses_pro_to_free(): void
    {
        $face = $this->makeFaceWithUser(['prenom' => 'Pro Expired Long Ago']);

        $photos = FacePhoto::factory()->createSequentialForFace($face, 4);
        foreach ($photos as $photo) {
            // Seed the grid/large variant columns + files so the purge is
            // proven to reclaim them too (interim image optimization).
            $photo->update([
                'grid' => "grid-{$photo->position}.webp",
                'large' => "large-{$photo->position}.webp",
            ]);
            $this->seedPhotoFilesOnDisk($photo);
        }

        FaceSubscription::factory()->pro()->expired()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subYear()->subDays(91),
            'expires_at' => now()->subDays(91),
        ]);

        $this->artisan('faces:purge-expired-media')
            ->expectsOutputToContain('Found 1 Face(s)')
            ->expectsOutputToContain("Purged face #{$face->id}: 3 photo(s), 0 acting video(s), 0 ugc video(s).")
            ->expectsOutputToContain('Done. Faces purged: 1, Photos: 3, Acting videos: 0, UGC videos: 0, Errored: 0.')
            ->assertExitCode(0);

        $remaining = FacePhoto::where('face_id', $face->id)->get();
        $this->assertSame(1, $remaining->count());
        $this->assertSame(1, $remaining->first()->position);

        $photosBy = $photos->keyBy('position');
        Storage::disk('public')->assertExists('avatars/faces/albums/'.$photosBy[1]->filename);
        Storage::disk('public')->assertExists('avatars/faces/albums/thumbnails/'.$photosBy[1]->thumbnail);
        Storage::disk('public')->assertExists('avatars/faces/albums/grid/grid-1.webp');
        Storage::disk('public')->assertExists('avatars/faces/albums/large/large-1.webp');
        Storage::disk('public')->assertMissing('avatars/faces/albums/'.$photosBy[2]->filename);
        Storage::disk('public')->assertMissing('avatars/faces/albums/thumbnails/'.$photosBy[2]->thumbnail);
        Storage::disk('public')->assertMissing('avatars/faces/albums/grid/grid-2.webp');
        Storage::disk('public')->assertMissing('avatars/faces/albums/large/large-2.webp');
        Storage::disk('public')->assertMissing('avatars/faces/albums/'.$photosBy[3]->filename);
        Storage::disk('public')->assertMissing('avatars/faces/albums/grid/grid-3.webp');
        Storage::disk('public')->assertMissing('avatars/faces/albums/large/large-3.webp');
        Storage::disk('public')->assertMissing('avatars/faces/albums/'.$photosBy[4]->filename);
        Storage::disk('public')->assertMissing('avatars/faces/albums/grid/grid-4.webp');
        Storage::disk('public')->assertMissing('avatars/faces/albums/large/large-4.webp');
    }

    public function test_command_purges_acting_and_ugc_videos_after_retention_window_elapses_elite_to_free(): void
    {
        $face = $this->makeFaceWithUser();

        $photos = FacePhoto::factory()->createSequentialForFace($face, 6);
        foreach ($photos as $photo) {
            $this->seedPhotoFilesOnDisk($photo);
        }

        $acting1 = FaceVideo::factory()->acting()->position(1)->create([
            'face_id' => $face->id,
            'filename' => 'acting-1.mp4',
            'thumbnail' => 'acting-1.jpg',
        ]);
        $acting2 = FaceVideo::factory()->acting()->position(2)->create([
            'face_id' => $face->id,
            'filename' => 'acting-2.mp4',
            'thumbnail' => 'acting-2.jpg',
        ]);
        $ugc = FaceVideo::factory()->ugc()->position(1)->create([
            'face_id' => $face->id,
            'filename' => 'ugc-1.mp4',
            'thumbnail' => 'ugc-1.jpg',
        ]);
        $this->seedVideoFilesOnDisk($acting1);
        $this->seedVideoFilesOnDisk($acting2);
        $this->seedVideoFilesOnDisk($ugc);

        FaceSubscription::factory()->elite()->expired()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subYear()->subDays(100),
            'expires_at' => now()->subDays(100),
        ]);

        $this->artisan('faces:purge-expired-media')
            ->expectsOutputToContain("Purged face #{$face->id}: 5 photo(s), 2 acting video(s), 1 ugc video(s).")
            ->expectsOutputToContain('Done. Faces purged: 1, Photos: 5, Acting videos: 2, UGC videos: 1, Errored: 0.')
            ->assertExitCode(0);

        $this->assertSame(1, FacePhoto::where('face_id', $face->id)->count());
        $this->assertSame(0, FaceVideo::where('face_id', $face->id)->count());

        Storage::disk('public')->assertMissing('videos/faces/acting/acting-1.mp4');
        Storage::disk('public')->assertMissing('videos/faces/acting/acting-2.mp4');
        Storage::disk('public')->assertMissing('videos/faces/ugc/ugc-1.mp4');
        Storage::disk('public')->assertMissing('videos/faces/acting/thumbnails/acting-1.jpg');
        Storage::disk('public')->assertMissing('videos/faces/ugc/thumbnails/ugc-1.jpg');
    }

    public function test_command_is_idempotent_on_consecutive_runs(): void
    {
        $face = $this->makeFaceWithUser();

        $photos = FacePhoto::factory()->createSequentialForFace($face, 4);
        foreach ($photos as $photo) {
            $this->seedPhotoFilesOnDisk($photo);
        }

        FaceSubscription::factory()->pro()->expired()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subYear()->subDays(91),
            'expires_at' => now()->subDays(91),
        ]);

        // First run purges 3 over-quota photos.
        $this->artisan('faces:purge-expired-media')
            ->expectsOutputToContain('Done. Faces purged: 1, Photos: 3, Acting videos: 0, UGC videos: 0, Errored: 0.')
            ->assertExitCode(0);

        $this->assertSame(1, FacePhoto::where('face_id', $face->id)->count());

        // Second run: 1 photo left at position 1 (within Free quota); nothing to purge.
        $this->artisan('faces:purge-expired-media')
            ->expectsOutputToContain('Done. Faces purged: 0, Photos: 0, Acting videos: 0, UGC videos: 0, Errored: 0.')
            ->assertExitCode(0);

        $this->assertSame(1, FacePhoto::where('face_id', $face->id)->count());
    }

    public function test_command_does_not_purge_when_face_re_subscribed_to_same_tier_within_window(): void
    {
        $face = $this->makeFaceWithUser();

        $photos = FacePhoto::factory()->createSequentialForFace($face, 4);
        foreach ($photos as $photo) {
            $this->seedPhotoFilesOnDisk($photo);
        }

        FaceSubscription::factory()->pro()->expired()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subYear()->subDays(100),
            'expires_at' => now()->subDays(100),
        ]);

        // Re-subscribed to Pro yesterday — Face is back on Pro (max=4 again).
        FaceSubscription::factory()->pro()->active()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addYear(),
        ]);

        $this->artisan('faces:purge-expired-media')
            ->expectsOutputToContain('Done. Faces purged: 0, Photos: 0, Acting videos: 0, UGC videos: 0, Errored: 0.')
            ->assertExitCode(0);

        $this->assertSame(4, FacePhoto::where('face_id', $face->id)->count());
    }

    public function test_command_does_not_purge_when_face_upgraded_to_higher_tier_after_expiration(): void
    {
        $face = $this->makeFaceWithUser();

        $photos = FacePhoto::factory()->createSequentialForFace($face, 4);
        foreach ($photos as $photo) {
            $this->seedPhotoFilesOnDisk($photo);
        }

        FaceSubscription::factory()->pro()->expired()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subYear()->subDays(100),
            'expires_at' => now()->subDays(100),
        ]);

        // Upgraded to Élite (max=6 covers all 4 photos).
        FaceSubscription::factory()->elite()->active()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addYear(),
        ]);

        $this->artisan('faces:purge-expired-media')
            ->expectsOutputToContain('Done. Faces purged: 0, Photos: 0, Acting videos: 0, UGC videos: 0, Errored: 0.')
            ->assertExitCode(0);

        $this->assertSame(4, FacePhoto::where('face_id', $face->id)->count());
    }

    public function test_command_purges_after_downgrade_via_chained_cancellation_when_window_elapsed(): void
    {
        $face = $this->makeFaceWithUser();

        $photos = FacePhoto::factory()->createSequentialForFace($face, 4);
        foreach ($photos as $photo) {
            $this->seedPhotoFilesOnDisk($photo);
        }

        // Old Pro Cancelled 100 days ago (chained downgrade event).
        FaceSubscription::factory()->pro()->cancelled()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subYear()->subDays(100),
            'expires_at' => now()->subDays(100)->addYear(),
            'cancelled_at' => now()->subDays(100),
        ]);

        // New Starter Active starting at old.cancelled_at — max=2 photos.
        FaceSubscription::factory()->starter()->active()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subDays(100),
            'expires_at' => now()->subDays(100)->addYear(),
        ]);

        $this->artisan('faces:purge-expired-media')
            ->expectsOutputToContain("Purged face #{$face->id}: 2 photo(s), 0 acting video(s), 0 ugc video(s).")
            ->expectsOutputToContain('Done. Faces purged: 1, Photos: 2, Acting videos: 0, UGC videos: 0, Errored: 0.')
            ->assertExitCode(0);

        $remaining = FacePhoto::where('face_id', $face->id)->orderBy('position')->get();
        $this->assertSame(2, $remaining->count());
        $this->assertSame(1, $remaining[0]->position);
        $this->assertSame(2, $remaining[1]->position);
    }

    public function test_command_does_not_purge_presentation_video_or_face_columns(): void
    {
        $face = $this->makeFaceWithUser([
            'presentation_video' => 'presentation-test.mp4',
            'presentation_video_thumbnail' => 'presentation-thumb.jpg',
        ]);

        Storage::disk('public')->put('videos/faces/presentation/presentation-test.mp4', 'pres video');
        Storage::disk('public')->put('videos/faces/presentation/thumbnails/presentation-thumb.jpg', 'pres thumb');

        FaceSubscription::factory()->pro()->expired()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subYear()->subDays(100),
            'expires_at' => now()->subDays(100),
        ]);

        $this->artisan('faces:purge-expired-media')->assertExitCode(0);

        $face->refresh();
        $this->assertSame('presentation-test.mp4', $face->presentation_video);
        $this->assertSame('presentation-thumb.jpg', $face->presentation_video_thumbnail);
        Storage::disk('public')->assertExists('videos/faces/presentation/presentation-test.mp4');
        Storage::disk('public')->assertExists('videos/faces/presentation/thumbnails/presentation-thumb.jpg');
    }

    public function test_command_sends_no_mail_no_notification_no_event(): void
    {
        // Targeted Event::fake on the 3 subscription lifecycle events so the
        // fake doesn't capture framework-internal eloquent/log events.
        Event::fake([
            \App\Events\FaceSubscriptionActivated::class,
            \App\Events\FaceSubscriptionExpired::class,
            \App\Events\FaceSubscriptionCancelled::class,
        ]);
        Mail::fake();
        Notification::fake();

        $face = $this->makeFaceWithUser();

        $photos = FacePhoto::factory()->createSequentialForFace($face, 4);
        foreach ($photos as $photo) {
            $this->seedPhotoFilesOnDisk($photo);
        }

        FaceSubscription::factory()->pro()->expired()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subYear()->subDays(91),
            'expires_at' => now()->subDays(91),
        ]);

        $this->artisan('faces:purge-expired-media')->assertExitCode(0);

        Event::assertNotDispatched(\App\Events\FaceSubscriptionActivated::class);
        Event::assertNotDispatched(\App\Events\FaceSubscriptionExpired::class);
        Event::assertNotDispatched(\App\Events\FaceSubscriptionCancelled::class);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();

        // Confirm the purge actually happened — proves the negative assertions
        // weren't trivially true because no work was done.
        $this->assertSame(1, FacePhoto::where('face_id', $face->id)->count());
    }

    public function test_command_writes_no_face_subscription_audit_row(): void
    {
        $face = $this->makeFaceWithUser();

        $photos = FacePhoto::factory()->createSequentialForFace($face, 4);
        foreach ($photos as $photo) {
            $this->seedPhotoFilesOnDisk($photo);
        }

        FaceSubscription::factory()->pro()->expired()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subYear()->subDays(91),
            'expires_at' => now()->subDays(91),
        ]);

        $before = FaceSubscriptionAudit::count();
        $this->artisan('faces:purge-expired-media')->assertExitCode(0);
        $after = FaceSubscriptionAudit::count();

        $this->assertSame(0, $before);
        $this->assertSame(0, $after);
    }

    public function test_log_payload_contains_face_and_media_type_for_each_purged_item(): void
    {
        $face = $this->makeFaceWithUser();

        $photos = FacePhoto::factory()->createSequentialForFace($face, 4);
        foreach ($photos as $photo) {
            $this->seedPhotoFilesOnDisk($photo);
        }

        FaceSubscription::factory()->pro()->expired()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subYear()->subDays(91),
            'expires_at' => now()->subDays(91),
        ]);

        // Expected over-quota photo IDs (positions 2, 3, 4 with Pro→Free max=1).
        $expectedIds = $photos
            ->filter(fn (FacePhoto $p) => $p->position > 1)
            ->pluck('id')
            ->all();

        Log::shouldReceive('info')
            ->times(3)
            ->withArgs(function (string $message, array $context) use ($face, $expectedIds): bool {
                return $message === 'Face media purged after retention window elapsed'
                    && ($context['face_id'] ?? null) === $face->id
                    && ($context['media_type'] ?? null) === 'album_photo'
                    && in_array($context['face_photo_id'] ?? null, $expectedIds, true)
                    && in_array($context['position'] ?? null, [2, 3, 4], true)
                    && is_string($context['retention_until'] ?? null);
            });

        $this->artisan('faces:purge-expired-media')->assertExitCode(0);
    }

    public function test_log_error_payload_fires_on_transaction_throw(): void
    {
        $face = $this->makeFaceWithUser();

        $photos = FacePhoto::factory()->createSequentialForFace($face, 4);
        foreach ($photos as $photo) {
            $this->seedPhotoFilesOnDisk($photo);
        }

        FaceSubscription::factory()->pro()->expired()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subYear()->subDays(91),
            'expires_at' => now()->subDays(91),
        ]);

        $shouldThrow = true;
        FacePhoto::deleting(function () use (&$shouldThrow): void {
            if ($shouldThrow) {
                $shouldThrow = false;
                throw new \RuntimeException('Forced delete failure for test');
            }
        });

        try {
            Log::shouldReceive('error')
                ->once()
                ->withArgs(function (string $message, array $context) use ($face): bool {
                    return $message === 'Face media purge failed'
                        && ($context['face_id'] ?? null) === $face->id
                        && array_key_exists('error_class', $context)
                        && array_key_exists('error_message', $context);
                });
            // Spec mandate (AC #10/#11/#12): per-item Log::info runs ONLY after
            // commit; a transaction throw must produce zero info entries.
            Log::shouldReceive('info')->never();

            $this->artisan('faces:purge-expired-media')
                ->expectsOutputToContain('Done. Faces purged: 0, Photos: 0, Acting videos: 0, UGC videos: 0, Errored: 1.')
                ->assertExitCode(1);

            // Transaction rolled back — all 4 photos still in DB.
            $this->assertSame(4, FacePhoto::where('face_id', $face->id)->count());

            // Spec mandate (Resolved Ambiguity #11): filesystem cleanup is NOT
            // attempted when the DB transaction fails — disk files survive intact.
            foreach ($photos as $photo) {
                Storage::disk('public')->assertExists('avatars/faces/albums/'.$photo->filename);
                if ($photo->thumbnail) {
                    Storage::disk('public')->assertExists('avatars/faces/albums/thumbnails/'.$photo->thumbnail);
                }
            }
        } finally {
            // Scoped cleanup: forget only the 'deleting' listener for FacePhoto
            // instead of flushing every FacePhoto event listener (avoids bleeding
            // into other tests that depend on globally-registered hooks).
            FacePhoto::getEventDispatcher()->forget('eloquent.deleting: '.FacePhoto::class);
        }
    }

    public function test_command_iterates_multiple_faces_in_id_order_with_per_face_summary(): void
    {
        // Face A: Pro Expired 50d ago + 4 photos → within-window, no purge.
        $faceA = $this->makeFaceWithUser(['prenom' => 'Face A']);
        $photosA = FacePhoto::factory()->createSequentialForFace($faceA, 4);
        foreach ($photosA as $photo) {
            $this->seedPhotoFilesOnDisk($photo);
        }
        FaceSubscription::factory()->pro()->expired()->create([
            'face_id' => $faceA->id,
            'starts_at' => now()->subYear()->subDays(50),
            'expires_at' => now()->subDays(50),
        ]);

        // Face B: Élite Expired 100d ago + 6 photos + 2 acting + 1 ugc → 5 + 2 + 1 purged.
        $faceB = $this->makeFaceWithUser(['prenom' => 'Face B']);
        $photosB = FacePhoto::factory()->createSequentialForFace($faceB, 6);
        foreach ($photosB as $photo) {
            $this->seedPhotoFilesOnDisk($photo);
        }
        $bActing1 = FaceVideo::factory()->acting()->position(1)->create(['face_id' => $faceB->id]);
        $bActing2 = FaceVideo::factory()->acting()->position(2)->create(['face_id' => $faceB->id]);
        $bUgc = FaceVideo::factory()->ugc()->position(1)->create(['face_id' => $faceB->id]);
        $this->seedVideoFilesOnDisk($bActing1);
        $this->seedVideoFilesOnDisk($bActing2);
        $this->seedVideoFilesOnDisk($bUgc);
        FaceSubscription::factory()->elite()->expired()->create([
            'face_id' => $faceB->id,
            'starts_at' => now()->subYear()->subDays(100),
            'expires_at' => now()->subDays(100),
        ]);

        // Face C: Starter Expired 95d ago + 2 photos → 1 photo purged (Free max=1).
        $faceC = $this->makeFaceWithUser(['prenom' => 'Face C']);
        $photosC = FacePhoto::factory()->createSequentialForFace($faceC, 2);
        foreach ($photosC as $photo) {
            $this->seedPhotoFilesOnDisk($photo);
        }
        FaceSubscription::factory()->starter()->expired()->create([
            'face_id' => $faceC->id,
            'starts_at' => now()->subYear()->subDays(95),
            'expires_at' => now()->subDays(95),
        ]);

        // Capture stdout via Artisan::call so we can assert iteration order
        // (PendingCommand's expectsOutputToContain is unordered).
        $exitCode = \Illuminate\Support\Facades\Artisan::call('faces:purge-expired-media');
        $this->assertSame(0, $exitCode);

        $output = \Illuminate\Support\Facades\Artisan::output();
        $this->assertStringContainsString('Found 3 Face(s)', $output);
        $this->assertStringContainsString("Purged face #{$faceB->id}: 5 photo(s), 2 acting video(s), 1 ugc video(s).", $output);
        $this->assertStringContainsString("Purged face #{$faceC->id}: 1 photo(s), 0 acting video(s), 0 ugc video(s).", $output);
        $this->assertStringContainsString('Done. Faces purged: 2, Photos: 6, Acting videos: 2, UGC videos: 1, Errored: 0.', $output);

        $bPos = strpos($output, "Purged face #{$faceB->id}");
        $cPos = strpos($output, "Purged face #{$faceC->id}");
        $this->assertNotFalse($bPos, 'Face B summary line missing from stdout.');
        $this->assertNotFalse($cPos, 'Face C summary line missing from stdout.');
        $this->assertLessThan(
            $cPos,
            $bPos,
            'Face B summary must precede Face C summary — command iterates by ascending Face id.',
        );

        // Face A untouched (within window) — DB rows + disk files all intact.
        $this->assertSame(4, FacePhoto::where('face_id', $faceA->id)->count());
        foreach ($photosA as $photo) {
            Storage::disk('public')->assertExists('avatars/faces/albums/'.$photo->filename);
            if ($photo->thumbnail) {
                Storage::disk('public')->assertExists('avatars/faces/albums/thumbnails/'.$photo->thumbnail);
            }
        }
        // Face B: 1 photo left, 0 videos.
        $this->assertSame(1, FacePhoto::where('face_id', $faceB->id)->count());
        $this->assertSame(0, FaceVideo::where('face_id', $faceB->id)->count());
        // Face C: 1 photo left.
        $this->assertSame(1, FacePhoto::where('face_id', $faceC->id)->count());
    }

    public function test_command_deletes_photo_medium_variant_when_present(): void
    {
        $face = $this->makeFaceWithUser();

        // Create 4 photos with explicit `medium` filenames so we can assert the
        // medium variant deletion path (`avatars/faces/albums/medium/...`).
        $photos = collect();
        for ($i = 1; $i <= 4; $i++) {
            $base = "medium-test-{$i}";
            $photos->push(FacePhoto::factory()->create([
                'face_id' => $face->id,
                'position' => $i,
                'filename' => "{$base}.jpg",
                'thumbnail' => "{$base}-thumb.jpg",
                'medium' => "{$base}-medium.jpg",
            ]));
        }
        foreach ($photos as $photo) {
            $this->seedPhotoFilesOnDisk($photo);
            Storage::disk('public')->assertExists('avatars/faces/albums/medium/'.$photo->medium);
        }

        FaceSubscription::factory()->pro()->expired()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subYear()->subDays(91),
            'expires_at' => now()->subDays(91),
        ]);

        $this->artisan('faces:purge-expired-media')->assertExitCode(0);

        // Photo at position 1 stays (Free tier max=1); positions 2-4 purged.
        $photosBy = $photos->keyBy('position');
        Storage::disk('public')->assertExists('avatars/faces/albums/medium/'.$photosBy[1]->medium);
        Storage::disk('public')->assertMissing('avatars/faces/albums/medium/'.$photosBy[2]->medium);
        Storage::disk('public')->assertMissing('avatars/faces/albums/medium/'.$photosBy[3]->medium);
        Storage::disk('public')->assertMissing('avatars/faces/albums/medium/'.$photosBy[4]->medium);
    }

    public function test_command_purges_when_face_downgraded_to_lower_active_tier_after_window(): void
    {
        $face = $this->makeFaceWithUser();

        // Pro Expired 100 days ago — entitlement gap, then Starter Active for 99 days.
        // Anchor = Pro.expires_at (100d ago) > 90d → window elapsed.
        // Current capabilities = Starter (max_album_photos = 2).
        $photos = FacePhoto::factory()->createSequentialForFace($face, 4);
        foreach ($photos as $photo) {
            $this->seedPhotoFilesOnDisk($photo);
        }

        FaceSubscription::factory()->pro()->expired()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subYear()->subDays(100),
            'expires_at' => now()->subDays(100),
        ]);

        FaceSubscription::factory()->starter()->active()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subDays(99),
            'expires_at' => now()->addDays(266),
        ]);

        $this->artisan('faces:purge-expired-media')
            ->expectsOutputToContain("Purged face #{$face->id}: 2 photo(s), 0 acting video(s), 0 ugc video(s).")
            ->expectsOutputToContain('Done. Faces purged: 1, Photos: 2, Acting videos: 0, UGC videos: 0, Errored: 0.')
            ->assertExitCode(0);

        $remaining = FacePhoto::where('face_id', $face->id)->orderBy('position')->get();
        $this->assertSame(2, $remaining->count(), 'Starter quota = 2 photos; positions 3 and 4 must be purged.');
        $this->assertSame(1, $remaining[0]->position);
        $this->assertSame(2, $remaining[1]->position);
    }

    public function test_storage_delete_returning_false_logs_warning_without_aborting_purge(): void
    {
        $face = $this->makeFaceWithUser();

        $photos = FacePhoto::factory()->createSequentialForFace($face, 4);
        foreach ($photos as $photo) {
            $this->seedPhotoFilesOnDisk($photo);
        }

        FaceSubscription::factory()->pro()->expired()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subYear()->subDays(91),
            'expires_at' => now()->subDays(91),
        ]);

        // Force Storage::disk('public')->delete to return false on every call to
        // simulate an FS permission glitch. exists() still returns true so the
        // command actually attempts the delete and observes the failure.
        $diskMock = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $diskMock->shouldReceive('exists')->andReturn(true);
        $diskMock->shouldReceive('delete')->andReturn(false);
        Storage::shouldReceive('disk')->with('public')->andReturn($diskMock);

        Log::shouldReceive('info')->times(3); // per-photo purge logs still fire
        Log::shouldReceive('warning')
            ->atLeast()->times(3) // one per photo's filename; thumbnail/medium add more if present
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Disk delete returned false after retention purge — disk file may be orphaned'
                    && array_key_exists('path', $context)
                    && array_key_exists('asset_kind', $context)
                    && array_key_exists('model_id', $context);
            });

        $this->artisan('faces:purge-expired-media')
            ->expectsOutputToContain('Done. Faces purged: 1, Photos: 3, Acting videos: 0, UGC videos: 0, Errored: 0.')
            ->assertExitCode(0);

        // DB delete still committed despite the disk-side warning.
        $this->assertSame(1, FacePhoto::where('face_id', $face->id)->count());
    }

    public function test_storage_delete_throwing_logs_warning_without_aborting_purge(): void
    {
        $face = $this->makeFaceWithUser();

        $photos = FacePhoto::factory()->createSequentialForFace($face, 4);
        foreach ($photos as $photo) {
            $this->seedPhotoFilesOnDisk($photo);
        }

        FaceSubscription::factory()->pro()->expired()->create([
            'face_id' => $face->id,
            'starts_at' => now()->subYear()->subDays(91),
            'expires_at' => now()->subDays(91),
        ]);

        $diskMock = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $diskMock->shouldReceive('exists')->andReturn(true);
        $diskMock->shouldReceive('delete')->andThrow(new \RuntimeException('Simulated disk outage'));
        Storage::shouldReceive('disk')->with('public')->andReturn($diskMock);

        Log::shouldReceive('info')->times(3);
        Log::shouldReceive('warning')
            ->atLeast()->times(3)
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Disk delete threw after retention purge — disk file may be orphaned'
                    && array_key_exists('path', $context)
                    && array_key_exists('asset_kind', $context)
                    && array_key_exists('model_id', $context)
                    && ($context['error_class'] ?? null) === \RuntimeException::class
                    && ($context['error_message'] ?? null) === 'Simulated disk outage';
            });

        $this->artisan('faces:purge-expired-media')
            ->expectsOutputToContain('Done. Faces purged: 1, Photos: 3, Acting videos: 0, UGC videos: 0, Errored: 0.')
            ->assertExitCode(0);

        // The DB purge is committed and the disk failure is captured as an
        // operator warning instead of aborting item logs/counters after commit.
        $this->assertSame(1, FacePhoto::where('face_id', $face->id)->count());
    }
}
