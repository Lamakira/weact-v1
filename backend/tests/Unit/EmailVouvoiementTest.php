<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Lot F3 : vouvoiement partout. Aucune vue d'e-mail, aucun Mailable ni aucune chaîne
 * de notification côté backend ne tutoie l'utilisateur.
 */
class EmailVouvoiementTest extends TestCase
{
    /** Pronoms/possessifs de 2e personne du singulier (hors mots composés ou de code). */
    private const TUTOIEMENT = "/(?<![\\p{L}\\p{N}_\$>-])(?:tu|toi|ton|ta|tes|t['’])(?![\\p{L}\\p{N}_])|(?<![\\p{L}\\p{N}_\$>-])te(?= [a-zéèêàâîôû])/iu";

    /** Impératifs au singulier relevés dans l'ancien registre. */
    private const IMPERATIFS = '/\b(?:Rejoins|Deviens|Confirme|Reprends|Envoie|Postule|Découvre|Inscris|Vérifie|Complète|Retrouve|Surveille|Apporte|Pense|Dépose|Redépose|Filme|Régularise|Consulte|Réessaie)\b/u';

    public function test_aucune_vue_d_email_ni_mailable_ne_tutoie(): void
    {
        $base = dirname(__DIR__, 2);
        $files = array_merge(
            $this->files($base.'/resources/views/emails'),
            $this->files($base.'/app/Mail'),
        );
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            foreach (explode("\n", (string) file_get_contents($file)) as $i => $line) {
                $trim = ltrim($line);
                if (str_starts_with($trim, '*') || str_starts_with($trim, '//') || str_starts_with($trim, '/*')) {
                    continue; // commentaires de code
                }
                $this->assertDoesNotMatchRegularExpression(self::TUTOIEMENT, $line, "{$file}:".($i + 1).' tutoie : '.trim($line));
                $this->assertDoesNotMatchRegularExpression(self::IMPERATIFS, $line, "{$file}:".($i + 1).' impératif au tu : '.trim($line));
            }
        }
    }

    public function test_les_notifications_et_messages_backend_ne_tutoient_pas(): void
    {
        $base = dirname(__DIR__, 2);
        $files = array_merge(
            $this->files($base.'/app/Listeners'),
            $this->files($base.'/app/Services'),
            $this->files($base.'/app/Http'),
            $this->files($base.'/app/Support'),
            $this->files($base.'/app/Notifications'),
        );
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            foreach (explode("\n", (string) file_get_contents($file)) as $i => $line) {
                $trim = ltrim($line);
                if (str_starts_with($trim, '*') || str_starts_with($trim, '//') || str_starts_with($trim, '/*')) {
                    continue;
                }
                // Seules les chaînes de caractères entre guillemets nous intéressent.
                if (! preg_match('/[\'"][^\'"]*[\'"]/u', $line)) {
                    continue;
                }
                $this->assertDoesNotMatchRegularExpression(self::TUTOIEMENT, $line, "{$file}:".($i + 1).' tutoie : '.trim($line));
            }
        }
    }

    /**
     * @return list<string>
     */
    private function files(string $dir): array
    {
        $out = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                $out[] = $file->getPathname();
            }
        }

        return $out;
    }
}
