<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Construit un header Content-Disposition RFC 6266 à partir d'un nom de
 * fichier UTF-8 quelconque (accents, espaces, caractères spéciaux) — #335.
 *
 * HeaderUtils::makeDisposition() exige un $filenameFallback ASCII : sans lui
 * fourni explicitement, il retombe sur $filename lui-même et lève une
 * InvalidArgumentException dès que ce nom contient un caractère non-ASCII
 * (ex: "Facture été.pdf") — provoquant une 500 sur tout téléchargement de
 * fichier accentué. BinaryFileResponse::setContentDisposition() évite ce
 * piège en générant sa propre translittération ASCII ; cette classe reproduit
 * la même stratégie pour les réponses (StreamedResponse) qui n'ont pas cette
 * méthode.
 */
final class ContentDispositionFactory
{
    public static function make(string $disposition, string $filename): string
    {
        $fallback = @iconv('UTF-8', 'ASCII//TRANSLIT', $filename);
        $fallback = is_string($fallback) ? $fallback : '';
        // Écarte tout ce que HeaderUtils::makeDisposition rejette encore dans
        // le fallback : hors imprimable ASCII, "%" et séparateurs de chemin.
        $fallback = (string) preg_replace('/[^\x20-\x7e]|[%\/\\\\]/', '_', $fallback);
        $fallback = trim($fallback) !== '' ? $fallback : 'download';

        return HeaderUtils::makeDisposition($disposition, $filename, $fallback);
    }
}
