<?php

declare(strict_types=1);

namespace App\Service\Takeout;

/**
 * Réassemble un fichier envoyé en tranches (#466) : un ZIP Google Takeout
 * peut dépasser 512M, plafond dur de l'hébergement mutualisé o2switch (non
 * contournable via .user.ini, confirmé en conditions réelles). Le front
 * découpe chaque fichier et envoie les tranches séquentiellement ; ce
 * service les écrit en append dans l'ordre strict attendu.
 *
 * Un fichier marqueur `<target>.progress` trace le dernier chunkIndex écrit
 * avec succès — plus fiable que de déduire la position depuis la taille du
 * fichier déjà écrit (la taille d'un chunk n'est pas garantie constante).
 */
final class ChunkedFileAssembler
{
    /**
     * @return bool true si ce chunk complète le fichier (dernier attendu)
     *
     * @throws \RuntimeException si le chunk arrive hors séquence
     */
    public function appendChunk(string $targetPath, int $chunkIndex, int $totalChunks, string $content): bool
    {
        $progressPath = $targetPath . '.progress';
        $lastWrittenIndex = is_file($progressPath) ? (int) file_get_contents($progressPath) : -1;

        // Idempotent sur retry réseau : le même chunk déjà écrit est un no-op
        // silencieux, jamais dupliqué.
        if ($chunkIndex <= $lastWrittenIndex) {
            return $lastWrittenIndex === $totalChunks - 1;
        }

        if ($chunkIndex !== $lastWrittenIndex + 1) {
            throw new \RuntimeException(sprintf(
                'Chunk %d arrived out of order (expected %d) for %s',
                $chunkIndex,
                $lastWrittenIndex + 1,
                $targetPath,
            ));
        }

        file_put_contents($targetPath, $content, FILE_APPEND);
        file_put_contents($progressPath, (string) $chunkIndex);

        $isComplete = $chunkIndex === $totalChunks - 1;
        if ($isComplete) {
            unlink($progressPath);
        }

        return $isComplete;
    }
}
