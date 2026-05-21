<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Non-destructive production data migration: WEACT is live and existing
     * Faces hold `faces.acting_video`. Each non-null acting video becomes a
     * `face_videos` row (type 'acting', position 1); the scalar columns are
     * then dropped. The `type` column stays a plain string (NFR1).
     */
    public function up(): void
    {
        DB::table('faces')
            ->whereNotNull('acting_video')
            ->where('acting_video', '!=', '')
            ->orderBy('id')
            ->each(function (object $face): void {
                DB::table('face_videos')->insert([
                    'uuid' => (string) Str::uuid(),
                    'face_id' => $face->id,
                    'type' => 'acting',
                    'filename' => $face->acting_video,
                    'thumbnail' => $face->acting_video_thumbnail,
                    'position' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('faces', function (Blueprint $table): void {
            $table->dropColumn(['acting_video', 'acting_video_thumbnail']);
        });
    }

    public function down(): void
    {
        Schema::table('faces', function (Blueprint $table): void {
            $table->string('acting_video', 255)->nullable()->after('presentation_video_thumbnail');
            $table->string('acting_video_thumbnail', 255)->nullable()->after('acting_video');
        });

        DB::table('face_videos')
            ->where('type', 'acting')
            ->where('position', 1)
            ->orderBy('id')
            ->each(function (object $video): void {
                DB::table('faces')
                    ->where('id', $video->face_id)
                    ->update([
                        'acting_video' => $video->filename,
                        'acting_video_thumbnail' => $video->thumbnail,
                    ]);
            });

        // Drop the rows just reinstated into faces.acting_video so a rollback
        // followed by a re-migrate does not re-insert them and collide on the
        // unique_face_video_type_position index.
        DB::table('face_videos')
            ->where('type', 'acting')
            ->where('position', 1)
            ->delete();
    }
};
