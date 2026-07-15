<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Interface\UserRepositoryInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Injecte `userGuests` dans tous les templates Twig pour l'utilisateur
 * connecté. Utilisé par ShareModal pour proposer la sélection directe d'un
 * invité déjà créé, sans ressaisir son email — même pattern que
 * FolderGlobalsExtension (userFolders).
 */
final class GuestGlobalsExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly Security $security,
    ) {}

    public function getGlobals(): array
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return ['userGuests' => []];
        }

        return [
            'userGuests' => $this->userRepository->findBy(
                ['accountType' => User::ACCOUNT_TYPE_GUEST],
                ['displayName' => 'ASC']
            ),
        ];
    }
}
