<?php

declare(strict_types=1);

namespace App\Interface\File;

use App\Entity\File;

/**
 * Contrat de suppression de fichier depuis l'interface web (session auth).
 * Absorbe la décision détacher-et-conserver (albums) vs suppression
 * complète, jusqu'ici en dur dans FileWebController::delete() — cf. #446
 * (suite #440, principe Controller = HTTP only).
 */
interface FileDeletionServiceInterface
{
    /**
     * @return bool true si le fichier a été détaché et conservé dans les
     *              albums (Media préservé), false si supprimé complètement
     * @throws \Throwable si la suppression physique ou la persistance échoue
     */
    public function deleteFile(File $file, bool $keepInAlbums): bool;
}
