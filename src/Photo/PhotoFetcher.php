<?php

namespace App\Photo;

use App\Entity\Photo;
use App\Entity\User;
use App\Repository\PhotoRepository;

class PhotoFetcher
{
    public function __construct(private PhotoRepository $photoRepository) {}

    /**
     * Retourne les photos de l'utilisateur connecté (triées par date desc)
     * @param User $user
     * @return Photo[]
     */
    public function forUser(User $user): array
    {
        return $this->photoRepository->findBy([
            'user' => $user
        ], [
            'uploadedAt' => 'DESC'
        ]);
    }
}
