<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Models\Face;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NormalizeRemainingFaceTerritoryValuesTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_05_400000_normalize_remaining_face_territory_values.php');
    }

    private function face(?string $nationalite, ?string $pays): int
    {
        $id = Face::factory()->create()->id;
        DB::table('faces')->where('id', $id)->update(['nationalite' => $nationalite, 'pays' => $pays]);

        return $id;
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string, 2: ?string, 3: ?string}>
     */
    public static function values(): array
    {
        return [
            'Beninoise' => ['Beninoise', 'Bénin', 'Béninoise', 'Bénin'],
            'Beninois' => ['Beninois', 'Bénin', 'Béninoise', 'Bénin'],
            'togolaise' => ['togolaise', 'Togo', 'Togolaise', 'Togo'],
            'Burkinabè' => ['Burkinabè', 'Burkina Faso', 'Burkinabé', 'Burkina Faso'],
            'Benin' => ['Béninoise', 'Benin', 'Béninoise', 'Bénin'],
            'TOGO' => ['Togolaise', 'TOGO', 'Togolaise', 'Togo'],
            'Côté d’Ivoire' => ['Ivoirienne', 'Côté d’Ivoire', 'Ivoirienne', 'Côte d’Ivoire'],
            'deux colonnes' => ['Beninoise', 'Benin', 'Béninoise', 'Bénin'],
        ];
    }

    #[DataProvider('values')]
    public function test_each_remaining_value_is_corrected(?string $nationalite, ?string $pays, ?string $expectedNationalite, ?string $expectedPays): void
    {
        $id = $this->face($nationalite, $pays);

        $this->migration()->up();

        $row = DB::table('faces')->where('id', $id)->first();
        $this->assertSame($expectedNationalite, $row->nationalite);
        $this->assertSame($expectedPays, $row->pays);
    }

    public function test_correct_values_are_neither_touched_nor_backed_up(): void
    {
        $id = $this->face('Béninoise', 'Bénin');

        $this->migration()->up();

        $this->assertFalse(DB::table('face_territory_backups')->where('face_id', $id)->exists());
    }

    public function test_the_original_values_are_backed_up_once(): void
    {
        $id = $this->face('Beninoise', 'Benin');

        $this->migration()->up();
        $this->migration()->up();

        $backups = DB::table('face_territory_backups')->where('face_id', $id)->get();
        $this->assertCount(1, $backups);
        $this->assertSame('Beninoise', $backups[0]->nationalite);
        $this->assertSame('Benin', $backups[0]->pays);
    }

    public function test_an_earlier_backup_is_kept(): void
    {
        $id = $this->face('Béninoise', 'Benin');
        DB::table('face_territory_backups')->insert(['face_id' => $id, 'nationalite' => 'Bénin', 'pays' => 'Benin', 'created_at' => now()]);

        $this->migration()->up();

        $this->assertSame('Bénin', DB::table('face_territory_backups')->where('face_id', $id)->value('nationalite'));
        $this->assertSame(1, DB::table('face_territory_backups')->where('face_id', $id)->count());
    }
}
