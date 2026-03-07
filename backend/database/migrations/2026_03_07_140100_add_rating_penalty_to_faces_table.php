<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faces', function (Blueprint $table): void {
            $table->float('rating_penalty')->default(0.0)->after('is_available');
        });
    }

    public function down(): void
    {
        Schema::table('faces', function (Blueprint $table): void {
            $table->dropColumn('rating_penalty');
        });
    }
};
