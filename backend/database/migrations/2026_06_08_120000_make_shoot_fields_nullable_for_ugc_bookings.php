<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * UGC dotations have no shoot date / duration / location (the Face films at home
     * on her own schedule). These "cash" fields were kept required as a strict superset
     * of the cash booking (story 1.1 AC5) — relax them to nullable for UGC.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dateTime('date_debut')->nullable()->change();
            $table->dateTime('date_fin')->nullable()->change();
            $table->unsignedInteger('duree_heures')->nullable()->change();
            // `lieu` is already nullable (migration 2026_04_10_100000).
        });

        // Clear vestigial shoot values on existing UGC bookings for consistency.
        DB::table('bookings')
            ->where('type_contenu', 'UGC')
            ->update([
                'date_debut' => null,
                'date_fin' => null,
                'duree_heures' => null,
                'lieu' => null,
            ]);
    }

    public function down(): void
    {
        // Backfill nulls before restoring NOT NULL, otherwise the tightening fails.
        DB::table('bookings')->whereNull('date_debut')->update(['date_debut' => DB::raw('created_at')]);
        DB::table('bookings')->whereNull('date_fin')->update(['date_fin' => DB::raw('created_at')]);
        DB::table('bookings')->whereNull('duree_heures')->update(['duree_heures' => 4]);

        Schema::table('bookings', function (Blueprint $table) {
            $table->dateTime('date_debut')->nullable(false)->change();
            $table->dateTime('date_fin')->nullable(false)->change();
            $table->unsignedInteger('duree_heures')->nullable(false)->change();
        });
    }
};
