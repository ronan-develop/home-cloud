<?php

declare(strict_types=1);

namespace App\Interface;

use Symfony\Component\Uid\Uuid;

/**
 * Contrat de nettoyage de tout partage (Share + ShareLink) pointant vers une
 * ressource supprimée. Respecte le Dependency Inversion Principle (SOLID D).
 */
interface SharedResourceCleanerInterface
{
    public function deleteByResource(string $resourceType, Uuid $resourceId): void;
}
