<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Album;
use App\Entity\File;
use App\Entity\Folder;
use App\Exception\ResourceNotPubliclyShareableException;

/**
 * Contrat du verrou de partage par lien public.
 * Respecte le Dependency Inversion Principle (SOLID D).
 */
interface VisibilityCheckerInterface
{
    /**
     * Une ressource est publiquement partageable si elle-même ET tous ses
     * parents (dossiers) sont `link_allowed` : le plus restrictif gagne.
     */
    public function isPubliclyShareable(File|Folder|Album $resource): bool;

    /**
     * @throws ResourceNotPubliclyShareableException si la ressource (ou un parent) est `private`
     */
    public function denyUnlessPubliclyShareable(File|Folder|Album $resource): void;
}
