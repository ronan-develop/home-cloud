<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Fait le pont entre le contrôleur d'upload et le listener kernel.terminate.
 *
 * Un fichier tout juste uploadé doit être traité (EXIF + vignette) dès que
 * possible plutôt que d'attendre le prochain cycle du worker Messenger, qui
 * peut prendre plusieurs minutes selon la charge du cron (cf. #251). Le
 * contrôleur enregistre l'id ici ; le listener le lit après l'envoi de la
 * réponse HTTP, sans faire attendre l'utilisateur.
 *
 * Un service normal suffit : une requête = un conteneur = une instance, pas
 * de fuite d'un utilisateur à l'autre.
 */
final class PendingMediaProcessingCollector
{
    /** @var list<string> */
    private array $fileIds = [];

    public function add(string $fileId): void
    {
        $this->fileIds[] = $fileId;
    }

    /**
     * @return list<string>
     */
    public function drain(): array
    {
        $fileIds = $this->fileIds;
        $this->fileIds = [];

        return $fileIds;
    }
}
