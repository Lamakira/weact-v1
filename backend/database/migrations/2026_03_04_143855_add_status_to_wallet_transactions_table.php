<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table): void {
            $table->enum('status', ['pending', 'completed', 'failed'])
                ->nullable()
                ->default(null)
                ->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table): void {
            $table->dropColumn('status');
        });
    }
};
