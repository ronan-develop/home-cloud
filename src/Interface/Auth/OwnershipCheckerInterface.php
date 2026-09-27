<?php

declare(strict_types=1);

namespace App\Interface\Auth;

use App\Entity\Album;
use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Share;
use App\Entity\ShareLink;
use App\Entity\TakeoutImport;

/**
 * Contrat de vérification de l'ownership des ressources.
 * Respecte le Dependency Inversion Principle (SOLID D).
 */
interface OwnershipCheckerInterface
{
    public function isOwner(Folder|Album|Share|File|ShareLink|TakeoutImport $resource): bool;

    /**
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException si non propriétaire
     */
    public function denyUnlessOwner(Folder|Album|Share|File|ShareLink|TakeoutImport $resource): void;
}
