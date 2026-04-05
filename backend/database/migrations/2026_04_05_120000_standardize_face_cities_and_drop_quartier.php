<?php

declare(strict_types=1);

use App\Constants\BeninCities;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('faces')
            ->select(['id', 'ville'])
            ->orderBy('id')
            ->chunkById(100, function ($faces): void {
                foreach ($faces as $face) {
                    DB::table('faces')
                        ->where('id', $face->id)
                        ->update([
                            'ville' => BeninCities::match($face->ville),
                        ]);
                }
            });

        Schema::table('faces', function (Blueprint $table): void {
            $table->dropColumn('quartier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('faces', function (Blueprint $table): void {
            $table->string('quartier', 100)->nullable()->after('ville');
        });
    }
};
