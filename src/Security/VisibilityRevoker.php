<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Album;
use App\Entity\File;
use App\Entity\Folder;
use App\Interface\ShareLinkRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Repasse une ressource en `private` et révoque en cascade tous ses liens
 * de partage publics actifs — le "bouton d'arrêt d'urgence" du plan de
 * partage. Sans la révocation en cascade, changer la visibilité ne ferait
 * qu'empêcher la création de FUTURS liens, en laissant les liens déjà émis
 * fonctionner jusqu'à leur expiration naturelle.
 */
final readonly class VisibilityRevoker
{
    public function __construct(
        private ShareLinkRepositoryInterface $shareLinkRepository,
        private EntityManagerInterface $em,
    ) {}

    public function makePrivate(File|Folder|Album $resource, string $resourceType, \Symfony\Component\Uid\Uuid $resourceId): void
    {
        $resource->setVisibility(match (true) {
            $resource instanceof File   => File::VISIBILITY_PRIVATE,
            $resource instanceof Folder => Folder::VISIBILITY_PRIVATE,
            $resource instanceof Album  => Album::VISIBILITY_PRIVATE,
        });

        foreach ($this->shareLinkRepository->findActiveByResource($resourceType, $resourceId) as $link) {
            $link->revoke();
        }

        $this->em->flush();
    }
}
