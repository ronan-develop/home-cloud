<?php

declare(strict_types=1);

namespace App\Interface;

/**
 * Cache des previews de fichiers RAW, redressées et redimensionnées.
 *
 * Abstraction volontaire : préparer une preview coûte environ une seconde, mais
 * la façon de la conserver (disque, mémoire, objet distant) ne regarde ni la
 * factory qui l'alimente, ni le service de suppression qui l'invalide.
 */
interface RawPreviewCacheInterface
{
    /**
     * @param string $sourceRelativePath Chemin relatif du RAW (ex: "2026/07/x.nef")
     *
     * @return string|null Les octets JPEG, ou null si absent du cache
     */
    public function get(string $sourceRelativePath): ?string;

    public function put(string $sourceRelativePath, string $jpegData): void;

    /**
     * Retire la preview du cache. Sans effet si rien n'est caché.
     */
    public function evict(string $sourceRelativePath): void;
}
