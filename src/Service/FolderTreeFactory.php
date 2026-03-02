<?php

namespace App\Service;

use App\Entity\Folder;
use App\Repository\FolderRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class FolderTreeFactory
{
    private FolderRepository $folderRepository;
    private UserRepository $userRepository;
    private EntityManagerInterface $em;

    public function __construct(FolderRepository $folderRepository, UserRepository $userRepository, EntityManagerInterface $em)
    {
        $this->folderRepository = $folderRepository;
        $this->userRepository = $userRepository;
        $this->em = $em;
    }

    /**
     * Crée la racine et les enfants par défaut si besoin
     * Retourne le dossier racine
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

        // Création automatique des enfants uniquement si la racine n'a aucun enfant
        if ($root && $root->getChildren()->count() === 0) {
            $toto = $this->folderRepository->findOneBy(['name' => 'toto', 'parent' => $root]);
            if (!$toto) {
                $toto = new Folder('toto', $root->getOwner(), $root);
                $this->em->persist($toto);
                $this->em->flush();
            }
            $tata = $this->folderRepository->findOneBy(['name' => 'tata', 'parent' => $toto]);
            if (!$tata) {
                $tata = new Folder('tata', $toto->getOwner(), $toto);
                $this->em->persist($tata);
                $this->em->flush();
            }
        }
        return $root;
    }
}
