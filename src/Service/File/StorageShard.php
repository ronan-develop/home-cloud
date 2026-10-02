<?php

declare(strict_types=1);

namespace App\Service\File;

/**
 * Répartition des fichiers du stockage en sous-dossiers (#609).
 *
 * Rôle : l'hébergement mutualisé o2switch interdit plus de 20 000 fichiers
 * dans un même répertoire. Un import volumineux rangé à plat (un seul mois,
 * un seul dossier de vignettes) dépasserait cette limite.
 *
 * Choix :
 * - Le shard est formé des 2 derniers caractères hexadécimaux du nom (hors
 *   extension) : la FIN d'un UUID v7 est aléatoire, alors que son début est un
 *   horodatage commun à tout un import. 256 sous-dossiers, ~400 fichiers
 *   chacun pour 100 000 photos.
 * - Le shard se déduit uniquement du nom de fichier : déterministe, donc la
 *   commande de migration est idempotente sans table d'état.
 * - Pas de date dans le chemin : opaque en base, elle créerait jusqu'à 256
 *   dossiers par mois, presque vides sur les petites instances (inodes).
 * - Un nom qui ne se termine pas par deux caractères hexadécimaux (fixtures,
 *   anciens noms) retombe sur un hash stable.
 */
final class StorageShard
{
    private const LEGACY_ORIGINAL_PATTERN = '#^\d{4}/\d{2}/[^/]+$#';
    private const LEGACY_THUMBNAIL_PATTERN = '#^thumbs/[^/]+$#';

    public static function forFilename(string $nameOrPath): string
    {
        $stem = pathinfo($nameOrPath, PATHINFO_FILENAME);

        if (preg_match('/([0-9a-fA-F]{2})$/', $stem, $m) === 1) {
            return strtolower($m[1]);
        }

        return substr(hash('xxh128', $stem), 0, 2);
    }

    /** Chemin relatif d'un original : `<shard>/<nom>`. */
    public static function originalPath(string $filename): string
    {
        return self::forFilename($filename).'/'.$filename;
    }

    /** Chemin relatif d'une vignette : `thumbs/<shard>/<nom>`. */
    public static function thumbnailPath(string $filename): string
    {
        return 'thumbs/'.self::forFilename($filename).'/'.$filename;
    }

    /**
     * Équivalent au nouveau format d'un chemin à l'ancien format
     * (`AAAA/MM/<nom>` ou `thumbs/<nom>`). Tout autre chemin, y compris un
     * chemin déjà réparti, est renvoyé tel quel.
     */
    public static function shardedPathOf(string $relativePath): string
    {
        if (preg_match(self::LEGACY_ORIGINAL_PATTERN, $relativePath) === 1) {
            return self::originalPath(basename($relativePath));
        }

        if (preg_match(self::LEGACY_THUMBNAIL_PATTERN, $relativePath) === 1) {
            return self::thumbnailPath(basename($relativePath));
        }

        return $relativePath;
    }
}
