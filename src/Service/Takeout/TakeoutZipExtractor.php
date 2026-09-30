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
 *
 * Checkpoint d'extraction (#525) : extractTo() est un bloc atomique sans
 * notion de progression — une coupure en plein milieu (LVE du mutualisé
 * o2switch, #520/#522) forçait à ré-extraire tout le ZIP depuis 0, perte de
 * travail potentiellement conséquente sur un ZIP volumineux déjà extrait à
 * 98%. Extraction entrée par entrée à la place, avec un marqueur
 * `<destinationDir>/.progress` traçant le dernier index extrait avec succès
 * — même pattern que ChunkedFileAssembler (#466) pour l'upload chunké.
 *
 * Batch limité par appel (#543 suite) : même avec le checkpoint, un ZIP à
 * plusieurs milliers d'entrées peut se faire tuer (SIGKILL LVE) plusieurs
 * fois de suite avant d'aboutir, chaque tentative perdant le temps déjà
 * écoulé dans ce même appel PHP. `extract()` s'arrête après
 * `maxEntriesPerCall` entrées et retourne `false` (extraction incomplète) ;
 * l'appelant (TakeoutImportExtractHandler) redispatche alors un nouveau
 * message pour continuer, au lieu d'attendre le prochain cycle
 * nightly-dispatch (jusqu'à 15 min).
 *
 * Vérification de sécurité sautée sur reprise (#546 suite) : assertSafeToExtract()
 * bouclait sur TOUTES les entrées à CHAQUE appel, y compris les reprises qui
 * ne traitent que maxEntriesPerCall entrées — réintroduisant le problème que
 * le batch limité devait résoudre (constaté en conditions réelles, un ZIP à
 * 1621 entrées continuait de se faire tuer). Les stats d'un ZIP ne changent
 * pas entre deux appels sur le même fichier : la vérification n'a de sens
 * qu'au tout premier appel (absence de .progress), jamais sur une reprise.
 */
class TakeoutZipExtractor
{
    public function __construct(
        private readonly int $maxTotalUncompressedBytes = 20 * 1024 * 1024 * 1024, // 20 Go
        private readonly int $maxCompressionRatio = 100,
        private readonly int $maxEntriesPerCall = 20,
    ) {}

    /**
     * @return bool true si le ZIP a été entièrement extrait, false s'il reste
     *              des entrées (limite maxEntriesPerCall atteinte) — à
     *              rappeler pour continuer, le checkpoint .progress reprendra
     *              automatiquement là où l'appel précédent s'est arrêté.
     *
     * @throws ZipBombDetectedException si l'archive dépasse les seuils de sécurité
     * @throws \RuntimeException si le fichier n'est pas un ZIP valide, ou si
     *                           une entrée échoue à s'extraire
     */
    public function extract(string $zipPath, string $destinationDir): bool
    {
        $zip = new \ZipArchive();
        $openResult = $zip->open($zipPath, \ZipArchive::RDONLY);
        if ($openResult !== true) {
            throw new \RuntimeException(sprintf('Impossible d\'ouvrir "%s" comme archive ZIP (code %d).', $zipPath, $openResult));
        }

        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0777, true);
        }

        $progressPath = $destinationDir . '/.progress';
        $isFirstCall = !is_file($progressPath);
        $lastExtractedIndex = $isFirstCall ? -1 : (int) file_get_contents($progressPath);

        if ($isFirstCall) {
            $this->assertSafeToExtract($zip);
        }

        $extractedThisCall = 0;
        $index = $lastExtractedIndex + 1;

        for (; $index < $zip->numFiles; ++$index) {
            if ($extractedThisCall >= $this->maxEntriesPerCall) {
                $zip->close();

                return false;
            }

            if (!$zip->extractTo($destinationDir, [$zip->getNameIndex($index)])) {
                $zip->close();
                throw new \RuntimeException(sprintf('Échec de l\'extraction de l\'entrée %d de "%s" vers "%s".', $index, $zipPath, $destinationDir));
            }

            file_put_contents($progressPath, (string) $index);
            ++$extractedThisCall;
        }

        if (is_file($progressPath)) {
            unlink($progressPath);
        }

        $zip->close();

        return true;
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
