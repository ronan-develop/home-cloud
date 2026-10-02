<?php

declare(strict_types=1);

namespace App\Service\Media;

use App\Interface\Media\RawPreviewCacheInterface;

/**
 * Cache disque des previews de fichiers RAW, redressées et redimensionnées.
 *
 * Rôle : préparer une preview coûte environ une seconde (décodage, rotation,
 * rééchantillonnage d'une image de 45 Mpx). Sans cache, un diaporama paierait
 * ce prix à chaque photo et à chaque passage.
 *
 * Choix :
 * - Le nom du fichier de cache est dérivé du chemin source, pas stocké en base :
 *   pas de migration ni de champ à maintenir, et le cache reste un détail
 *   d'implémentation qu'on peut vider entièrement sans rien casser.
 * - Le hash aplatit le nom en un nom de fichier unique, ce qui évite au passage
 *   toute traversée de répertoire.
 * - #609 : le hash porte sur le NOM du fichier source (`<uuid>.<ext>`, stable),
 *   pas sur son chemin complet, sinon déplacer l'original (app:storage:shard)
 *   orphelinerait sa preview. Les entrées sont réparties en `previews/<xx>/`
 *   (xx = 2 premiers caractères du hash) pour rester sous les 20 000 fichiers
 *   par répertoire de l'hébergeur.
 * - Dégradation gracieuse : un cache illisible ou non inscriptible (disque
 *   plein, droits) fait retomber sur une génération à la volée, jamais sur une
 *   erreur.
 */
final readonly class RawPreviewCache implements RawPreviewCacheInterface
{
    private const CACHE_SUBDIR = 'previews';

    public function __construct(
        private string $storageDir,
    ) {}

    /**
     * @param string $sourceRelativePath Chemin relatif du RAW (ex: "2026/07/x.nef")
     *
     * @return string|null Les octets JPEG, ou null si absent du cache
     */
    public function get(string $sourceRelativePath): ?string
    {
        $path = $this->pathFor($sourceRelativePath);

        if (!is_file($path)) {
            return null;
        }

        $data = @file_get_contents($path);

        return $data === false ? null : $data;
    }

    public function put(string $sourceRelativePath, string $jpegData): void
    {
        $dir = dirname($this->pathFor($sourceRelativePath));

        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return;
        }

        // Écriture atomique : sans elle, deux requêtes simultanées sur la même
        // photo pourraient servir un fichier tronqué.
        $tmp = $this->pathFor($sourceRelativePath) . '.' . uniqid('', true) . '.tmp';

        if (@file_put_contents($tmp, $jpegData) === false) {
            return;
        }

        if (!@rename($tmp, $this->pathFor($sourceRelativePath))) {
            @unlink($tmp);
        }
    }

    /**
     * Retire la preview du cache. Sans effet si rien n'est caché — la méthode
     * est appelée à chaque suppression de média, y compris pour les JPEG qui
     * n'ont jamais de preview.
     */
    public function evict(string $sourceRelativePath): void
    {
        foreach ([$this->pathFor($sourceRelativePath), $this->legacyPathFor($sourceRelativePath)] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function purgeLegacyFlatEntries(): int
    {
        $purged = 0;

        foreach (glob($this->cacheDir() . '/*.jpg') ?: [] as $file) {
            if (@unlink($file)) {
                ++$purged;
            }
        }

        return $purged;
    }

    private function pathFor(string $sourceRelativePath): string
    {
        $hash = hash('xxh128', basename($sourceRelativePath));

        return $this->cacheDir() . '/' . substr($hash, 0, 2) . '/' . $hash . '.jpg';
    }

    /** Format d'avant #609 : à plat, haché sur le chemin complet. */
    private function legacyPathFor(string $sourceRelativePath): string
    {
        return $this->cacheDir() . '/' . hash('xxh128', $sourceRelativePath) . '.jpg';
    }

    private function cacheDir(): string
    {
        return $this->storageDir . '/' . self::CACHE_SUBDIR;
    }
}
