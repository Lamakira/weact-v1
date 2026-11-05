<?php

declare(strict_types=1);

use App\Support\Territories;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Valeurs restées hors liste après 2026_11_05_200000 : le comptage de production passait par un
 * GROUP BY en collation _ci, qui les fusionnait avec leur forme accentuée. Comparaison exacte ici.
 * Les valeurs d'origine vont dans face_territory_backups, dont la migration précédente sait
 * restaurer le contenu : pas de retour arrière propre à celle-ci.
 */
return new class extends Migration
{
    private const MAPS = [
        'nationalite' => [
            'Beninoise' => 'Béninoise',
            'Beninois' => 'Béninoise',
            'togolaise' => 'Togolaise',
            'Burkinabè' => 'Burkinabé',
        ],
        'pays' => [
            'Benin' => 'Bénin',
            'TOGO' => 'Togo',
            'Côté d’Ivoire' => 'Côte d’Ivoire',
        ],
    ];

    public function up(): void
    {
        $this->assertTargetsExist();

        foreach (self::MAPS as $column => $map) {
            foreach ($map as $from => $to) {
                $faces = DB::table('faces')->whereRaw("BINARY `{$column}` = ?", [$from])->get(['id', 'nationalite', 'pays']);

                foreach ($faces as $face) {
                    DB::transaction(function () use ($face, $column, $from, $to): void {
                        if (! DB::table('face_territory_backups')->where('face_id', $face->id)->exists()) {
                            DB::table('face_territory_backups')->insert([
                                'face_id' => $face->id,
                                'nationalite' => $face->nationalite,
                                'pays' => $face->pays,
                                'created_at' => now(),
                            ]);
                        }

                        DB::table('faces')
                            ->where('id', $face->id)
                            ->whereRaw("BINARY `{$column}` = ?", [$from])
                            ->update([$column => $to]);
                    });
                }
            }
        }
    }

    public function down(): void
    {
        // Voir le docblock : la restauration passe par la migration 2026_11_05_200000.
    }

    private function assertTargetsExist(): void
    {
        $lists = ['nationalite' => Territories::nationalities(), 'pays' => Territories::countries()];

        foreach (self::MAPS as $column => $map) {
            foreach ($map as $to) {
                if (! in_array($to, $lists[$column], true)) {
                    throw new RuntimeException("Valeur cible absente de la liste : {$to}");
                }
            }
        }
    }
};
