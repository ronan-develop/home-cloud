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
    // Dupliqué depuis CHUNK_SIZE_BYTES de assets/js/chunked-upload.js (garder
    // synchronisé) : taille du "premier chunk" hashé par resumeState() pour
    // que le client vérifie qu'il reprend bien le même fichier avant de
    // continuer l'écriture en append (#481 — reprise après fermeture
    // d'onglet en cours d'upload).
    public const CHUNK_SIZE_BYTES = 50 * 1024 * 1024;

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

    /**
     * État de reprise d'un fichier partiellement uploadé, ou null si rien à
     * reprendre (aucun octet écrit, ou fichier déjà complet — le marqueur
     * .progress est supprimé par appendChunk() à la complétion).
     *
     * @return array{chunkIndex: int, hashOfFirstChunk: ?string}|null
     */
    public function resumeState(string $targetPath): ?array
    {
        $progressPath = $targetPath . '.progress';
        if (!is_file($progressPath) || !is_file($targetPath)) {
            return null;
        }

        $firstChunkBytes = file_get_contents($targetPath, false, null, 0, self::CHUNK_SIZE_BYTES);

        return [
            'chunkIndex' => (int) file_get_contents($progressPath),
            'hashOfFirstChunk' => $firstChunkBytes !== false ? hash('sha256', $firstChunkBytes) : null,
        ];
    }
}
