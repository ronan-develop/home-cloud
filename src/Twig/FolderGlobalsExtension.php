<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Repository\FolderRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Injecte `userFolders` dans tous les templates Twig pour l'utilisateur connecté.
 * Utilisé par le modal de sélection de dossier dans layout.html.twig.
 */
final class FolderGlobalsExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly FolderRepository $folderRepository,
        private readonly Security $security,
    ) {}

    public function getGlobals(): array
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return ['userFolders' => []];
        }

        return [
            'userFolders' => $this->folderRepository->findBy(
                ['owner' => $user],
                ['name' => 'ASC']
            ),
        ];
    }
}
