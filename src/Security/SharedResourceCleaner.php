<?php

declare(strict_types=1);

namespace App\Security;

use App\Interface\ShareLinkRepositoryInterface;
use App\Interface\SharedResourceCleanerInterface;
use App\Interface\ShareRepositoryInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Point d'entrée unique pour nettoyer TOUT partage (Share + ShareLink)
 * pointant vers une ressource supprimée.
 *
 * Avant l'introduction de ShareLink, chaque appelant appelait directement
 * ShareRepository::deleteByResource(). Ajouter ShareLink en dupliquant l'appel
 * à chaque site aurait risqué d'en oublier un — ce service centralise les deux
 * suppressions pour qu'un futur 3e mécanisme de partage n'ait qu'un seul
 * endroit à modifier.
 */
final readonly class SharedResourceCleaner implements SharedResourceCleanerInterface
{
    public function __construct(
        private ShareRepositoryInterface $shareRepository,
        private ShareLinkRepositoryInterface $shareLinkRepository,
    ) {}

    public function deleteByResource(string $resourceType, Uuid $resourceId): void
    {
        $this->shareRepository->deleteByResource($resourceType, $resourceId);
        $this->shareLinkRepository->deleteByResource($resourceType, $resourceId);
    }
}
