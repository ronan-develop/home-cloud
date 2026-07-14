<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Album;
use App\Entity\File;
use App\Entity\Folder;
use Symfony\Component\Uid\Uuid;

/**
 * Contrat de résolution d'une ressource polymorphe (resourceType + resourceId).
 * Respecte le Dependency Inversion Principle (SOLID D).
 */
interface ResourceLocatorInterface
{
    public function locate(string $resourceType, Uuid $resourceId): File|Folder|Album;
}
