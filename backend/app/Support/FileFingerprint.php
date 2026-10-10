<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Empreinte (inode, taille, mtime) d'un fichier : permet de vérifier, juste avant
 * un rename, que l'original n'a été ni supprimé ni remplacé pendant un
 * nettoyage (sinon on ressusciterait un orphelin à son URL publique).
 */
final class FileFingerprint
{
    /**
     * @return array{int, int, int}|null
     */
    public static function of(string $path): ?array
    {
        clearstatcache(true, $path);
        $stat = @stat($path);

        return $stat === false ? null : [$stat['ino'], $stat['size'], $stat['mtime']];
    }
}
