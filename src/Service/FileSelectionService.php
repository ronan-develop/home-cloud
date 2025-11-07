<?php

namespace App\Service;

use App\Entity\File;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class FileSelectionService
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Retourne les fichiers appartenant à l'utilisateur parmi une liste d'IDs
     * @param array $ids Liste des IDs de fichiers
     * @param User $user Utilisateur courant
     * @return File[]
     */
    public function getUserFilesByIds(array $ids, User $user): array
    {
        if (empty($ids)) {
            return [];
        }
        return $this->em->getRepository(File::class)->findBy([
            'id' => $ids,
            'owner' => $user
        ]);
    }
}
