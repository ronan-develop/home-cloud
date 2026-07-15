<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Album;
use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Share;
use Symfony\Component\Uid\Uuid;

/**
 * Vérifie qu'un fichier fait bien partie du périmètre d'un lien de partage
 * public, pour empêcher qu'un porteur de lien pivote vers une autre ressource
 * de l'owner en changeant simplement l'id de fichier dans l'URL.
 *
 * - lien sur un File   : seul ce fichier
 * - lien sur un Folder : tout fichier dans ce dossier OU l'un de ses
 *   sous-dossiers, à n'importe quelle profondeur — cohérent avec
 *   VisibilityChecker, qui autorise déjà ces fichiers par remontée des
 *   ancêtres (partager Docs/ doit aussi permettre de télécharger
 *   Docs/Sous/fichier.txt, pas seulement de le voir listé).
 * - lien sur un Album  : tout fichier qui est le support d'un Media de l'album
 */
final readonly class SharedFileScopeChecker
{
    public function isInScope(File $file, string $linkResourceType, Uuid $linkResourceId, File|Folder|Album $linkResource): bool
    {
        return match ($linkResourceType) {
            Share::RESOURCE_FILE   => $file->getId()->equals($linkResourceId),
            Share::RESOURCE_FOLDER => $this->isFileUnderFolder($file, $linkResourceId),
            Share::RESOURCE_ALBUM  => $this->isFileInAlbum($file, $linkResource),
            default => false,
        };
    }

    private function isFileUnderFolder(File $file, Uuid $linkedFolderId): bool
    {
        $folder = $file->getFolder();
        while ($folder !== null) {
            if ($folder->getId()->equals($linkedFolderId)) {
                return true;
            }
            $folder = $folder->getParent();
        }

        return false;
    }

    private function isFileInAlbum(File $file, File|Folder|Album $linkResource): bool
    {
        if (!$linkResource instanceof Album) {
            return false;
        }

        foreach ($linkResource->getMedias() as $media) {
            if ($media->getFile()->getId()->equals($file->getId())) {
                return true;
            }
        }

        return false;
    }
}
