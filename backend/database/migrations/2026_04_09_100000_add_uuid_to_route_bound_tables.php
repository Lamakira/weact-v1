<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->uuid('uuid')->nullable()->unique()->after('id');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('uuid');
            });
        }
    }
};
