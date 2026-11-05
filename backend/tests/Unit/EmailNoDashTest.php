<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Aucun tiret quadratin (—) ni demi-cadratin (–) dans le texte reçu par un destinataire :
 * vues d'e-mail, Mailables (sujets compris) et notifications mail.
 */
class EmailNoDashTest extends TestCase
{
    public function test_aucun_tiret_cadratin_dans_les_vues_les_mailables_et_les_notifications(): void
    {
        $base = dirname(__DIR__, 2);
        $files = array_merge(
            $this->files($base.'/resources/views/emails'),
            $this->files($base.'/app/Mail'),
            $this->files($base.'/app/Notifications'),
        );
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $isPhp = str_ends_with($file, '.php') && ! str_ends_with($file, '.blade.php');
            foreach (explode("\n", (string) file_get_contents($file)) as $i => $line) {
                $trim = ltrim($line);
                if ($isPhp && (str_starts_with($trim, '*') || str_starts_with($trim, '//') || str_starts_with($trim, '/*'))) {
                    continue; // commentaires et docblocks PHP
                }
                $this->assertDoesNotMatchRegularExpression('/[—–]/u', $line, "{$file}:".($i + 1).' contient un tiret cadratin : '.trim($line));
            }
        }
    }

    /** @return list<string> */
    private function files(string $dir): array
    {
        $out = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
                $out[] = $f->getPathname();
            }
        }

        return $out;
    }
}
