<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Album;
use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Share;

/**
 * Contrat de vérification de l'ownership des ressources.
 * Respecte le Dependency Inversion Principle (SOLID D).
 */
interface OwnershipCheckerInterface
{
    public function isOwner(Folder|Album|Share|File $resource): bool;

    /**
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException si non propriétaire
     */
    public function denyUnlessOwner(Folder|Album|Share|File $resource): void;
}
