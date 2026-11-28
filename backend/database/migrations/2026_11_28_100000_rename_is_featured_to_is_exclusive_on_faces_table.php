<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * « En vedette » devient « Exclusivité Élite » (ADR 0001) : la vitrine repart
 * vide, l'admin choisit ses 4 premières exclusivités. Le down() rétablit le
 * schéma, pas les anciennes coches (elles sont perdues par décision).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faces', function (Blueprint $table): void {
            $table->dropIndex('faces_is_featured_index');
        });

        Schema::table('faces', function (Blueprint $table): void {
            $table->renameColumn('is_featured', 'is_exclusive');
        });

        Schema::table('faces', function (Blueprint $table): void {
            $table->index('is_exclusive', 'faces_is_exclusive_index');
        });

        DB::table('faces')->update(['is_exclusive' => false]);
    }

    public function down(): void
    {
        Schema::table('faces', function (Blueprint $table): void {
            $table->dropIndex('faces_is_exclusive_index');
        });

        Schema::table('faces', function (Blueprint $table): void {
            $table->renameColumn('is_exclusive', 'is_featured');
        });

        Schema::table('faces', function (Blueprint $table): void {
            $table->index('is_featured', 'faces_is_featured_index');
        });
    }
};
