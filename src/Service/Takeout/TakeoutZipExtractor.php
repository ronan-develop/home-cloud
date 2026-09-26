<?php

declare(strict_types=1);

namespace App\Service\Takeout;

use App\Exception\Takeout\ZipBombDetectedException;

/**
 * Extraction d'un ZIP Google Takeout sur disque temporaire (#327).
 *
 * Path traversal : protection native de ZipArchive::extractTo() (vérifié
 * empiriquement sur PHP 8.4.23 — un entry "../../etc/passwd" reste confiné
 * dans le dossier cible), pas de logique supplémentaire nécessaire ici.
 *
 * Zip bomb : PHP n'a aucune protection native. Les stats de chaque entrée
 * (taille annoncée, compressée et décompressée) sont lisibles via
 * ZipArchive::statIndex() AVANT toute extraction réelle — la vérification
 * se fait donc entièrement sur les métadonnées de l'archive, jamais après
 * avoir écrit quoi que ce soit sur disque.
 */
class TakeoutZipExtractor
{
    public function __construct(
        private readonly int $maxTotalUncompressedBytes = 20 * 1024 * 1024 * 1024, // 20 Go
        private readonly int $maxCompressionRatio = 100,
    ) {}

    /**
     * @throws ZipBombDetectedException si l'archive dépasse les seuils de sécurité
     * @throws \RuntimeException si le fichier n'est pas un ZIP valide
     */
    public function extract(string $zipPath, string $destinationDir): void
    {
        $zip = new \ZipArchive();
        $openResult = $zip->open($zipPath, \ZipArchive::RDONLY);
        if ($openResult !== true) {
            throw new \RuntimeException(sprintf('Impossible d\'ouvrir "%s" comme archive ZIP (code %d).', $zipPath, $openResult));
        }

        $this->assertSafeToExtract($zip);

        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0777, true);
        }

        if (!$zip->extractTo($destinationDir)) {
            $zip->close();
            throw new \RuntimeException(sprintf('Échec de l\'extraction de "%s" vers "%s".', $zipPath, $destinationDir));
        }

        $zip->close();
    }

    /**
     * @throws ZipBombDetectedException
     */
    private function assertSafeToExtract(\ZipArchive $zip): void
    {
        $totalUncompressed = 0;

        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }

            $uncompressedSize = $stat['size'];
            $compressedSize = $stat['comp_size'];

            $totalUncompressed += $uncompressedSize;

            if ($compressedSize > 0) {
                $ratio = $uncompressedSize / $compressedSize;
                if ($ratio > $this->maxCompressionRatio) {
                    $zip->close();
                    throw new ZipBombDetectedException(sprintf(
                        'Ratio de compression suspect (%.0f:1) sur "%s" — extraction refusée.',
                        $ratio,
                        $stat['name'],
                    ));
                }
            }

            if ($totalUncompressed > $this->maxTotalUncompressedBytes) {
                $zip->close();
                throw new ZipBombDetectedException(sprintf(
                    'Taille décompressée totale (%d octets) dépasse le plafond autorisé (%d octets) — extraction refusée.',
                    $totalUncompressed,
                    $this->maxTotalUncompressedBytes,
                ));
            }
        }
    }
}
