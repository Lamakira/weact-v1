<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Accounts created through an identity provider (Google) never set a password.
     * `users.password` NOT NULL is the single schema-level blocker for that.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * The backfill is mandatory: without it the rollback fails on any OAuth-only
     * row. An unguessable random password is written rather than a known value.
     */
    public function down(): void
    {
        DB::table('users')
            ->whereNull('password')
            ->update(['password' => Hash::make(Str::random(40))]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
