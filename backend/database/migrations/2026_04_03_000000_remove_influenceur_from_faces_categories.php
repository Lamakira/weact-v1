<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove "influenceur" from all faces' categories JSON arrays.
     *
     * Temporary cleanup until the influencer verification workflow is implemented.
     * The FaceCategory::INFLUENCEUR enum case is kept for future reactivation.
     */
    public function up(): void
    {
        DB::statement("
            UPDATE faces
            SET categories = JSON_REMOVE(
                categories,
                REPLACE(JSON_SEARCH(categories, 'one', 'influenceur'), '\"', '')
            )
            WHERE JSON_CONTAINS(categories, '\"influenceur\"')
        ");
    }

    /**
     * Re-add "influenceur" is not feasible — we don't know which faces had it.
     * This migration is intentionally not reversible.
     */
    public function down(): void
    {
        // No rollback — data was already cleaned in production via manual SQL.
    }
};
