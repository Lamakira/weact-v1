<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE missions MODIFY COLUMN status ENUM('draft', 'published', 'pending_payment', 'closed', 'pending_attendance_validation', 'completed') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE missions MODIFY COLUMN status ENUM('draft', 'published', 'pending_payment', 'closed', 'completed') NOT NULL DEFAULT 'draft'");
    }
};
