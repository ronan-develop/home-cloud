<?php

declare(strict_types=1);

namespace App\Service;

use App\Interface\RawPreviewCacheInterface;

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
 * - Le hash aplatit le chemin (qui contient des slashes) en un nom de fichier
 *   unique, ce qui évite au passage toute traversée de répertoire.
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
        $dir = $this->cacheDir();

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
        $path = $this->pathFor($sourceRelativePath);

        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function pathFor(string $sourceRelativePath): string
    {
        return $this->cacheDir() . '/' . hash('xxh128', $sourceRelativePath) . '.jpg';
    }

    private function cacheDir(): string
    {
        return $this->storageDir . '/' . self::CACHE_SUBDIR;
    }
}
