<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Détecte l'en-tête `%PDF-` en tolérant qu'il soit décalé dans le fichier,
 * comme le font les vrais lecteurs PDF (ISO 32000-1 §7.5.2 : l'en-tête peut
 * apparaître n'importe où dans les 1024 premiers octets). `finfo`/libmagic,
 * plus strict selon la version installée, peut détecter à tort
 * `application/octet-stream` sur un PDF pourtant valide et lisible.
 */
final class PdfSignatureDetector
{
    private const SIGNATURE = '%PDF-';
    private const TOLERANCE_BYTES = 1024;

    public function detect(string $absolutePath): bool
    {
        $head = @file_get_contents($absolutePath, false, null, 0, self::TOLERANCE_BYTES);

        return $head !== false && str_contains($head, self::SIGNATURE);
    }
}
