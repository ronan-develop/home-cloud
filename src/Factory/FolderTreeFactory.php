<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Folder;
use App\Repository\FolderRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Crée l'arborescence de dossiers par défaut si elle n'existe pas encore.
 */
class FolderTreeFactory
{
    public function __construct(
        private readonly FolderRepository $folderRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Crée le dossier racine "Dossiers" s'il n'existe pas encore pour le premier utilisateur.
     * Utilisé uniquement au premier affichage du navigateur de dossiers.
     */
    public function ensureDefaultTree(): ?Folder
    {
        $root = $this->folderRepository->findOneBy(['name' => 'Dossiers', 'parent' => null]);
        if (!$root) {
            $user = $this->userRepository->findOneBy([]);
            if ($user) {
                $root = new Folder('Dossiers', $user, null);
                $this->em->persist($root);
                $this->em->flush();
            }
        }

        return $root;
    }
}
