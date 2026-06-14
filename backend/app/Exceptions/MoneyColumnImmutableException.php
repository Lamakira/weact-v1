<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Levée lorsqu'une colonne de montant est modifiée après la création du modèle.
 * Les colonnes financières sont posées une fois à la création puis immuables (hardening ugc-3-5).
 */
class MoneyColumnImmutableException extends \RuntimeException {}
