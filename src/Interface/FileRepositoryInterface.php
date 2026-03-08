<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\File;
use Symfony\Component\Uid\Uuid;

/**
 * Contrat pour l'accès aux données File.
 *
 * Dépendre de cette interface permet de mocker le repository en tests
 * et de swapper l'implémentation sans toucher aux consommateurs.
 */
interface FileRepositoryInterface
{
    /**
     * Find a file by ID.
     *
     * @return File|null
     */
    public function findById(Uuid $id): ?File;
}
