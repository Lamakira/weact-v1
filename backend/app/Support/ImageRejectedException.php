<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Image refusée pour une raison que l'utilisateur doit comprendre (message français
 * affichable tel quel dans l'erreur de validation), par opposition aux
 * RuntimeException génériques « fichier corrompu ».
 */
final class ImageRejectedException extends \RuntimeException {}
