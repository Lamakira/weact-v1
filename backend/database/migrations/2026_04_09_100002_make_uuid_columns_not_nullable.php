<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Backfills any missing UUIDs before making the route UUID columns NOT NULL.
 */
return new class extends Migration
{
    private const CHUNK_SIZE = 500;

    private const TABLES = [
        'bookings',
        'missions',
        'candidatures',
        'conversations',
        'experiences',
        'face_photos',
        'notifications',
        'faces',
        'producers',
        'articles',
        'admins',
        'withdrawal_requests',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            $this->backfillMissingUuids($table);

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->uuid('uuid')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->uuid('uuid')->nullable()->change();
            });
        }
    }

    private function backfillMissingUuids(string $table): void
    {
        DB::table($table)
            ->whereNull('uuid')
            ->select('id')
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function ($records) use ($table): void {
                foreach ($records as $record) {
                    DB::table($table)
                        ->where('id', $record->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            }, 'id');
    }
};
