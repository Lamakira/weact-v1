<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faces', function (Blueprint $table) {
            // LRU fairness state for the public-listing rotation: when this
            // Face last appeared in the "page 1" window (first 15 ranks) of a
            // materialized listing generation. NULL = never exposed, which
            // sorts FIRST in each tier queue (new-profile boost).
            $table->timestamp('last_page1_exposed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('faces', function (Blueprint $table) {
            $table->dropColumn('last_page1_exposed_at');
        });
    }
};
