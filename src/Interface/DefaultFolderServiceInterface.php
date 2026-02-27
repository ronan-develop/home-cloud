<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Folder;
use App\Entity\User;

/**
 * Contrat pour la résolution du dossier de destination d'un fichier uploadé.
 *
 * Dépendre de cette interface permet de mocker la logique de résolution
 * en tests et de swapper l'implémentation (règles métier différentes par user).
 */
interface DefaultFolderServiceInterface
{
    /**
     * Résout le dossier de destination selon la priorité :
     *   1. $folderId fourni → dossier existant (appartenant à $owner)
     *   2. $newFolderName fourni → crée un nouveau dossier
     *   3. Aucun → retourne/crée le dossier système "Uploads"
     *
     * @throws \InvalidArgumentException si le folderId n'existe pas ou n'appartient pas à $owner
     */
    public function resolve(?string $folderId, ?string $newFolderName, User $owner): Folder;
}
