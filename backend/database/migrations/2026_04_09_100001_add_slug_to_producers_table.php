<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producers', function (Blueprint $table): void {
            $table->string('slug')->nullable()->unique()->after('uuid');
        });

        DB::table('producers')
            ->select(['id', 'type', 'agency_name', 'first_name', 'last_name'])
            ->whereNull('slug')
            ->orderBy('id')
            ->chunkById(500, function ($producers): void {
                foreach ($producers as $producer) {
                    $displayName = $producer->type === 'agency'
                        ? (string) ($producer->agency_name ?: '')
                        : trim((string) ($producer->first_name ?: '').' '.(string) ($producer->last_name ?: ''));

                    $base = Str::slug($displayName);
                    $slug = $base !== '' ? $base : 'producer';
                    $counter = 1;

                    while (DB::table('producers')
                        ->where('slug', $slug)
                        ->where('id', '!=', $producer->id)
                        ->exists()) {
                        $slug = ($base !== '' ? $base : 'producer')."-{$counter}";
                        $counter++;
                    }

                    DB::table('producers')
                        ->where('id', $producer->id)
                        ->update(['slug' => $slug]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('producers', function (Blueprint $table): void {
            $table->dropColumn('slug');
        });
    }
};
