<?php
declare(strict_types=1);

namespace App\Interface;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Contrat pour les vérifications d'autorisation (ownership, cycles, etc.).
 *
 * Dépendre de cette interface permet de mocker les vérifications en tests
 * et de swapper l'implémentation sans toucher aux consommateurs.
 */
interface AuthorizationCheckerInterface
{
    /**
     * Check if user owns the entity.
     *
     * @throws AccessDeniedHttpException
     */
    public function assertOwns(File|Folder $entity, User $requester): void;

    /**
     * Check if moving targetFolder under sourceFolder would create a cycle.
     *
     * @return bool true if cycle would be created
     */
    public function wouldCreateCycle(Folder $sourceFolder, Folder $targetFolder): bool;
}
