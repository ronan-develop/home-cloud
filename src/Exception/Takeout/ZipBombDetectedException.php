<?php

declare(strict_types=1);

namespace App\Exception\Takeout;

/**
 * Levée quand un ZIP dépasse le plafond de taille décompressée totale ou
 * qu'une entrée présente un ratio de compression anormal — jamais extrait
 * sur disque avant cette vérification (#327, TakeoutZipExtractor).
 */
final class ZipBombDetectedException extends \RuntimeException
{
}
