<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Album;
use App\Entity\File;
use App\Entity\Folder;
use App\Exception\ResourceNotPubliclyShareableException;
use App\Interface\VisibilityCheckerInterface;

/**
 * Verrou de partage par lien public : le serveur refuse de rendre une
 * ressource publiquement accessible tant que l'owner n'a pas explicitement
 * basculé sa visibilité en `link_allowed` — sur la ressource elle-même ET
 * sur toute la chaîne de ses dossiers parents (le plus restrictif gagne).
 *
 * Sans cette remontée des parents, déplacer un fichier "link_allowed" dans un
 * dossier "private" ne le protégerait pas : le verrou ne vaudrait plus rien.
 */
final readonly class VisibilityChecker implements VisibilityCheckerInterface
{
    public function isPubliclyShareable(File|Folder|Album $resource): bool
    {
        if ($resource->getVisibility() !== Folder::VISIBILITY_LINK_ALLOWED) {
            return false;
        }

        if ($resource instanceof Album) {
            return true;
        }

        $parent = $resource instanceof File ? $resource->getFolder() : $resource->getParent();
        while ($parent !== null) {
            if ($parent->getVisibility() !== Folder::VISIBILITY_LINK_ALLOWED) {
                return false;
            }
            $parent = $parent->getParent();
        }

        return true;
    }

    public function denyUnlessPubliclyShareable(File|Folder|Album $resource): void
    {
        if (!$this->isPubliclyShareable($resource)) {
            throw new ResourceNotPubliclyShareableException();
        }
    }
}
