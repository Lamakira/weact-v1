<?php

declare(strict_types=1);

use App\Models\Admin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Les tokens Admin émis avant le plafond de 12 h n'ont pas de `expires_at` et
 * restaient valides 30 jours. On leur donne `created_at + 12 h` : ceux de plus de
 * 12 h expirent immédiatement. Aucune morph map n'est définie : le type stocké est
 * le nom de classe.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('personal_access_tokens')
            ->where('tokenable_type', Admin::class)
            ->whereNull('expires_at')
            ->update(['expires_at' => DB::raw('DATE_ADD(created_at, INTERVAL '.Admin::TOKEN_TTL_HOURS.' HOUR)')]);
    }

    public function down(): void
    {
        // Irréversible : on ne peut pas retrouver quels tokens n'avaient pas d'expiration.
    }
};
