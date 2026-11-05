<?php

declare(strict_types=1);

use App\Support\Territories;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Passe les champs libres faces.nationalite / faces.pays sur les listes fermées
 * (App\Support\Territories). Mapping exact des valeurs rencontrées en production
 * le 2026-11-05 ; toute autre valeur est laissée telle quelle (la validation
 * tolère une ancienne valeur identique à celle stockée).
 *
 * Écrit par DB::table (pas d'événements, updated_at inchangé). Les lignes
 * modifiées sont sauvegardées d'abord dans face_territory_backups ; down()
 * les restaure puis supprime la table.
 */
return new class extends Migration
{
    private const CONGO_KINSHASA = 'Congo-Kinshasa';

    private const CONGO_BRAZZAVILLE = 'Congo-Brazzaville';

    private const NATIONALITE_CONGO_KINSHASA = 'Congolaise (République démocratique du Congo)';

    private const NATIONALITE_CONGO_BRAZZAVILLE = 'Congolaise (République du Congo)';

    /** @var array<string, string|null> */
    private const NATIONALITE_MAP = [
        'Béninois' => 'Béninoise',
        'Beninoi' => 'Béninoise',
        'Bénin' => 'Béninoise',
        'Béninoise, Nigériane' => 'Béninoise',
        'Bénino-Togolais' => 'Béninoise',
        'Benino franco Gabonaise' => 'Béninoise',
        'Togolais' => 'Togolaise',
        'Camerounais' => 'Camerounaise',
        'Libanais' => 'Libanaise',
        'Nigerian' => 'Nigériane',
        'Îles Cook' => null,
        '' => null,
    ];

    /** @var array<string, string|null> */
    private const PAYS_MAP = [
        'Béni' => 'Bénin',
        'Béninoise' => 'Bénin',
        'Bénin 🇧🇯' => 'Bénin',
        '🇨🇦' => 'Canada',
        'Kuwait' => 'Koweït',
        'Mali, Bénin' => 'Mali',
        "Côte d'Ivoire" => 'Côte d’Ivoire',
        '' => null,
    ];

    public function up(): void
    {
        $this->assertTargetsExist();

        if (! Schema::hasTable('face_territory_backups')) {
            Schema::create('face_territory_backups', function (Blueprint $table) {
                $table->unsignedBigInteger('face_id')->index();
                $table->string('nationalite', 100)->nullable();
                $table->string('pays', 100)->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        $rows = DB::table('faces')
            ->where(function ($query) {
                $query->whereNotNull('nationalite')->orWhereNotNull('pays');
            })
            ->get(['id', 'nationalite', 'pays']);

        foreach ($rows as $row) {
            $nationalite = $row->nationalite;
            $pays = $row->pays;

            $newNationalite = $this->mapNationalite($nationalite, $pays);
            $newPays = $this->mapPays($pays);

            if ($newNationalite === $nationalite && $newPays === $pays) {
                continue;
            }

            DB::transaction(function () use ($row, $nationalite, $pays, $newNationalite, $newPays) {
                // On garde la toute première valeur d'origine d'une Face.
                if (! DB::table('face_territory_backups')->where('face_id', $row->id)->exists()) {
                    DB::table('face_territory_backups')->insert([
                        'face_id' => $row->id,
                        'nationalite' => $nationalite,
                        'pays' => $pays,
                        'created_at' => now(),
                    ]);
                }

                // BINARY : comparaison exacte, la collation _ci confond accents, casse et emojis.
                $query = DB::table('faces')->where('id', $row->id);
                $this->whereExactly($query, 'nationalite', $nationalite);
                $this->whereExactly($query, 'pays', $pays);
                $query->update(['nationalite' => $newNationalite, 'pays' => $newPays]);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('face_territory_backups')) {
            return;
        }

        foreach (DB::table('face_territory_backups')->get() as $backup) {
            DB::table('faces')->where('id', $backup->face_id)->update([
                'nationalite' => $backup->nationalite,
                'pays' => $backup->pays,
            ]);
        }

        Schema::drop('face_territory_backups');
    }

    private function mapNationalite(?string $nationalite, ?string $pays): ?string
    {
        if ($nationalite === null) {
            return null;
        }

        if ($nationalite === 'congolaise') {
            return match ($pays) {
                self::CONGO_KINSHASA => self::NATIONALITE_CONGO_KINSHASA,
                self::CONGO_BRAZZAVILLE => self::NATIONALITE_CONGO_BRAZZAVILLE,
                default => null,
            };
        }

        return array_key_exists($nationalite, self::NATIONALITE_MAP)
            ? self::NATIONALITE_MAP[$nationalite]
            : $nationalite;
    }

    private function mapPays(?string $pays): ?string
    {
        if ($pays === null) {
            return null;
        }

        return array_key_exists($pays, self::PAYS_MAP) ? self::PAYS_MAP[$pays] : $pays;
    }

    private function whereExactly(\Illuminate\Database\Query\Builder $query, string $column, ?string $value): void
    {
        if ($value === null) {
            $query->whereNull($column);

            return;
        }

        $query->whereRaw("BINARY `{$column}` = ?", [$value]);
    }

    private function assertTargetsExist(): void
    {
        $nationalities = Territories::nationalities();
        $countries = Territories::countries();

        $targets = [
            [array_filter(array_values(self::NATIONALITE_MAP)), $nationalities],
            [[self::NATIONALITE_CONGO_KINSHASA, self::NATIONALITE_CONGO_BRAZZAVILLE], $nationalities],
            [array_filter(array_values(self::PAYS_MAP)), $countries],
        ];

        foreach ($targets as [$values, $list]) {
            foreach ($values as $value) {
                if (! in_array($value, $list, true)) {
                    throw new RuntimeException("Valeur cible absente de la liste : {$value}");
                }
            }
        }
    }
};
