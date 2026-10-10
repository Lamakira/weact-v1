<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `google_id` holds Google's opaque `sub` claim.
     *
     * The index is UNIQUE, not a plain index: one Google identity must map to at
     * most one WEACT user. MySQL allows unlimited NULLs in a unique index, so
     * password-only accounts are unaffected. 64 chars fits `sub` with headroom and
     * stays well under the 3072-byte index limit at utf8mb4.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id', 64)->nullable()->unique()->after('password');
            $table->timestamp('google_linked_at')->nullable()->after('google_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn(['google_id', 'google_linked_at']);
        });
    }
};
