<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE escrow_transactions MODIFY status ENUM('pending', 'locked', 'released', 'refunded') NOT NULL DEFAULT 'locked'");
    }

    public function down(): void
    {
        DB::statement("UPDATE escrow_transactions SET status = 'refunded' WHERE status = 'pending'");
        DB::statement("ALTER TABLE escrow_transactions MODIFY status ENUM('locked', 'released', 'refunded') NOT NULL DEFAULT 'locked'");
    }
};
