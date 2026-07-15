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
 * ressource publiquement accessible tant que l'owner n'a jamais autorisé
 * `link_allowed`, ni sur la ressource elle-même, ni sur l'un de ses
 * dossiers ancêtres — sur ce point c'est un OU, pas un ET.
 *
 * Trois mécanismes de partage indépendants s'appuient sur cette même règle :
 * - partage d'un fichier isolé (icône sur sa vignette) : ne dépend jamais de
 *   son dossier parent, même s'il est private.
 * - partage d'un dossier (icône en haut à droite dans l'explorateur) :
 *   rend accessible tout son contenu, récursivement, y compris les fichiers
 *   ajoutés après coup — vérifié dynamiquement à chaque requête (remontée
 *   des ancêtres), jamais par une mise à jour en masse au moment du clic.
 * - partage d'un album : inchangé, un album n'a pas de parent.
 *
 * Une ressource est donc publiquement partageable si ELLE-MÊME est
 * `link_allowed`, OU si l'un de ses ancêtres (remontée complète de la
 * chaîne des dossiers) l'est. Un sous-dossier `link_allowed` reste un point
 * d'entrée de partage valide même si son propre parent est `private` : on
 * peut partager `Docs/Factures/` sans jamais toucher à `Docs/`.
 */
final readonly class VisibilityChecker implements VisibilityCheckerInterface
{
    public function isPubliclyShareable(File|Folder|Album $resource): bool
    {
        if ($resource->getVisibility() === Folder::VISIBILITY_LINK_ALLOWED) {
            return true;
        }

        if ($resource instanceof Album) {
            return false;
        }

        $parent = $resource instanceof File ? $resource->getFolder() : $resource->getParent();
        while ($parent !== null) {
            if ($parent->getVisibility() === Folder::VISIBILITY_LINK_ALLOWED) {
                return true;
            }
            $parent = $parent->getParent();
        }

        return false;
    }

    public function denyUnlessPubliclyShareable(File|Folder|Album $resource): void
    {
        if (!$this->isPubliclyShareable($resource)) {
            throw new ResourceNotPubliclyShareableException();
        }
    }
}
