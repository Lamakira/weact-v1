<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliverables', function (Blueprint $table) {
            // Ancre du SLA 48 h Producteur (D-4.3.g) : moment d'entrée — ou
            // ré-entrée après re-upload — en in_review. DISTINCTE du chrono Face
            // (chrono_started_at). Nullable car (re)posée serveur à chaque upload.
            $table->timestamp('submitted_at')->nullable()->after('validated_at');
        });

        // Backfill rétroactif (règle migration rétroactive) : les lignes Unboxing
        // créées en 4.1 (avant cette colonne) prennent leur created_at comme ancre
        // SLA — created_at = moment d'entrée en in_review pour ces lignes.
        DB::statement('UPDATE deliverables SET submitted_at = created_at WHERE submitted_at IS NULL');
    }

    public function down(): void
    {
        Schema::table('deliverables', function (Blueprint $table) {
            $table->dropColumn('submitted_at');
        });
    }
};
