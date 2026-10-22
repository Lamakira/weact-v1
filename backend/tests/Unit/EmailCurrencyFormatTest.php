<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Lot F1 : aucun montant d'e-mail ne doit afficher « XOF »/« FCFA » ni être formaté à la main.
 * Les montants passent par App\Support\Money::format (« 12 500 F CFA »).
 */
class EmailCurrencyFormatTest extends TestCase
{
    public function test_aucune_vue_ni_mailable_n_affiche_xof_ou_fcfa(): void
    {
        $base = dirname(__DIR__, 2);
        $files = array_merge(
            $this->files($base.'/resources/views/emails', 'php'),
            $this->files($base.'/app/Mail', 'php'),
        );

        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $content = (string) file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression('/\bXOF\b|\bFCFA\b/', $content, "{$file} affiche un code devise brut.");
            $this->assertStringNotContainsString('number_format(', $content, "{$file} formate un montant à la main : utiliser Money::format.");
        }
    }

    /**
     * @return list<string>
     */
    private function files(string $dir, string $extension): array
    {
        $out = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), $extension)) {
                $out[] = $file->getPathname();
            }
        }

        return $out;
    }
}
