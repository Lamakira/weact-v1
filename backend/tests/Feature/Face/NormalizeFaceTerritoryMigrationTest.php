<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Models\Face;
use App\Support\Territories;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NormalizeFaceTerritoryMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const FILE = '2026_11_05_200000_normalize_face_nationalite_and_pays.php';

    private function migration(): Migration
    {
        return require database_path('migrations/'.self::FILE);
    }

    private function face(?string $nationalite, ?string $pays): int
    {
        $id = Face::factory()->create()->id;
        DB::table('faces')->where('id', $id)->update(['nationalite' => $nationalite, 'pays' => $pays]);

        return $id;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function row(int $id): array
    {
        $row = DB::table('faces')->where('id', $id)->first();

        return [$row->nationalite, $row->pays];
    }

    public function test_nationalite_mapping_cases(): void
    {
        $cases = [
            ['Béninois', 'Bénin', 'Béninoise'],
            ['Beninoi', 'Bénin', 'Béninoise'],
            ['Bénin', 'Bénin', 'Béninoise'],
            ['Béninoise, Nigériane', 'Bénin', 'Béninoise'],
            ['Bénino-Togolais', 'Togo', 'Béninoise'],
            ['Benino franco Gabonaise', 'France', 'Béninoise'],
            ['Togolais', 'Togo', 'Togolaise'],
            ['Camerounais', 'Cameroun', 'Camerounaise'],
            ['Libanais', 'Liban', 'Libanaise'],
            ['Nigerian', 'Nigeria', 'Nigériane'],
            ['Liberiane', 'Bénin', 'Libérienne'],
            ['Sierra Léonaise', 'Bénin', 'Sierra-Léonaise'],
            ['congolaise', 'Congo-Kinshasa', 'Congolaise (République démocratique du Congo)'],
            ['congolaise', 'Congo-Brazzaville', 'Congolaise (République du Congo)'],
            ['congolaise', 'France', null],
            ['Îles Cook', 'Bénin', null],
            ['', 'Bénin', null],
        ];

        $ids = [];
        foreach ($cases as $i => [$source, $pays]) {
            $ids[$i] = $this->face($source, $pays);
        }

        $this->migration()->up();

        foreach ($cases as $i => [$source, $pays, $expected]) {
            $this->assertSame([$expected, $pays], $this->row($ids[$i]), "nationalite {$source} ({$pays})");
        }
    }

    public function test_pays_mapping_cases(): void
    {
        $cases = [
            ['Béni', 'Bénin'],
            ['Béninoise', 'Bénin'],
            ['Bénin 🇧🇯', 'Bénin'],
            ['🇨🇦', 'Canada'],
            ['Kuwait', 'Koweït'],
            ['Mali, Bénin', 'Mali'],
            ["Côte d'Ivoire", 'Côte d’Ivoire'],
            ['', null],
        ];

        $ids = [];
        foreach ($cases as $i => [$source]) {
            $ids[$i] = $this->face('Béninoise', $source);
        }

        $this->migration()->up();

        foreach ($cases as $i => [$source, $expected]) {
            $this->assertSame(['Béninoise', $expected], $this->row($ids[$i]), "pays {$source}");
        }
    }

    public function test_emoji_flags_are_not_confused_by_collation(): void
    {
        // Une Face au drapeau béninois ne doit pas être traitée comme le drapeau canadien.
        $canada = $this->face('Béninoise', '🇨🇦');
        $other = $this->face('Béninoise', '🇧🇯');

        $this->migration()->up();

        $this->assertSame('Canada', $this->row($canada)[1]);
        $this->assertSame('🇧🇯', $this->row($other)[1]);
        $this->assertSame(1, DB::table('face_territory_backups')->count());
    }

    public function test_already_correct_values_are_untouched_and_not_backed_up(): void
    {
        $ok = $this->face('Béninoise', 'Bénin');
        $unknown = $this->face('Nom inconnu', 'Pays inconnu');
        $nulls = $this->face(null, null);
        $changed = $this->face('Togolais', 'Togo');

        $this->migration()->up();

        $this->assertSame(['Béninoise', 'Bénin'], $this->row($ok));
        $this->assertSame(['Nom inconnu', 'Pays inconnu'], $this->row($unknown));
        $this->assertSame([null, null], $this->row($nulls));
        $this->assertSame(['Togolaise', 'Togo'], $this->row($changed));

        $this->assertSame([$changed], DB::table('face_territory_backups')->pluck('face_id')->all());
        $backup = DB::table('face_territory_backups')->first();
        $this->assertSame('Togolais', $backup->nationalite);
        $this->assertSame('Togo', $backup->pays);
        $this->assertNotNull($backup->created_at);
    }

    public function test_does_not_touch_updated_at(): void
    {
        $id = $this->face('Togolais', 'Togo');
        DB::table('faces')->where('id', $id)->update(['updated_at' => '2026-01-01 10:00:00']);

        $this->migration()->up();

        $this->assertSame('2026-01-01 10:00:00', DB::table('faces')->where('id', $id)->value('updated_at'));
    }

    public function test_rerun_has_no_effect(): void
    {
        $id = $this->face('congolaise', 'Congo-Kinshasa');

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame(['Congolaise (République démocratique du Congo)', 'Congo-Kinshasa'], $this->row($id));
        $this->assertSame(1, DB::table('face_territory_backups')->count());
        $this->assertSame('congolaise', DB::table('face_territory_backups')->value('nationalite'));
    }

    public function test_every_target_value_is_in_the_lists(): void
    {
        // up() lève si une cible est absente des listes : l'appel suffit à le prouver.
        $this->migration()->up();

        $this->assertContains('Béninoise', Territories::nationalities());
        $this->assertContains('Bénin', Territories::countries());
        $this->assertContains('Côte d’Ivoire', Territories::countries());
    }

    public function test_down_restores_from_backup_and_drops_the_table(): void
    {
        $a = $this->face('Béninois', 'Béni');
        $b = $this->face('congolaise', 'Congo-Brazzaville');
        $untouched = $this->face('Béninoise', 'Bénin');
        $ids = [$a, $b, $untouched];

        try {
            $this->migration()->up();
            $this->assertSame(['Béninoise', 'Bénin'], $this->row($a));

            // DDL : COMMIT implicite en MySQL, d'où le nettoyage explicite plus bas.
            $this->migration()->down();

            $this->assertSame(['Béninois', 'Béni'], $this->row($a));
            $this->assertSame(['congolaise', 'Congo-Brazzaville'], $this->row($b));
            $this->assertSame(['Béninoise', 'Bénin'], $this->row($untouched));
            $this->assertFalse(Schema::hasTable('face_territory_backups'));
        } finally {
            DB::table('faces')->whereIn('id', $ids)->delete();
            if (! Schema::hasTable('face_territory_backups')) {
                $this->migration()->up();
            }
        }
    }
}
