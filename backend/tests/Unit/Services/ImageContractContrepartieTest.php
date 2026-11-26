<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ImageContract\ImageContractService;
use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ImageContractContrepartieTest extends TestCase
{
    /**
     * @return array<string, array{0: array<string, mixed>, 1: list<string>}>
     */
    public static function sharedCases(): array
    {
        $cases = json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/image-contract-contrepartie.json'), true, 512, JSON_THROW_ON_ERROR);

        $out = [];
        foreach ($cases as $case) {
            $out[$case['name']] = [$case['contrepartie'], $case['lines']];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $contrepartie
     * @param  list<string>  $lines
     */
    #[DataProvider('sharedCases')]
    public function test_the_lines_match_the_shared_fixture(array $contrepartie, array $lines): void
    {
        $this->assertSame($lines, ImageContractService::contrepartieLines($contrepartie));
    }

    public function test_an_old_cash_snapshot_without_cachet_keeps_the_old_line(): void
    {
        $this->assertSame(
            ['Rémunération reçue par la Face : '.Money::format(90000)],
            ImageContractService::contrepartieLines(['kind' => 'cash', 'montant_face_recoit' => 90000]),
        );
    }

    public function test_an_old_hybrid_snapshot_without_cachet_keeps_the_product_and_the_old_line(): void
    {
        $this->assertSame(
            [
                'Produit remis : Tenue Shade Fit (valeur '.Money::format(20000).')',
                'Rémunération reçue par la Face : '.Money::format(13500),
            ],
            ImageContractService::contrepartieLines([
                'kind' => 'ugc_hybrid',
                'nom_produit' => 'Tenue Shade Fit',
                'valeur_produit' => 20000,
                'montant_face_recoit' => 13500,
            ]),
        );
    }

    public function test_the_net_line_is_for_the_parent_only_and_needs_a_cachet(): void
    {
        $this->assertSame(
            'La Face reçoit '.Money::format(90000),
            ImageContractService::netLine(['kind' => 'cash', 'cachet' => 100000, 'montant_face_recoit' => 90000]),
        );
        $this->assertNull(ImageContractService::netLine(['kind' => 'ugc_product', 'nom_produit' => 'x', 'valeur_produit' => 1]));
        $this->assertNull(ImageContractService::netLine(['kind' => 'cash', 'montant_face_recoit' => 90000]));
        $this->assertNull(ImageContractService::netLine(['kind' => 'cash', 'cachet' => 100000, 'montant_face_recoit' => 0]));
    }
}
