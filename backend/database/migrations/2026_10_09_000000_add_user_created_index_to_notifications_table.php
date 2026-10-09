<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The notifications index orders a user's rows by created_at (latest first);
     * only (user_id, read_at) was indexed, so MySQL sorted every row of the user.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->index(['user_id', 'created_at'], 'notifications_user_id_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex('notifications_user_id_created_at_index');
        });
    }
};
