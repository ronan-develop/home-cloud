<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AlbumOutput;
use App\Entity\Album;
use App\Entity\User;
use App\Repository\AlbumRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Fournit les données lues pour les opérations GET sur la ressource Album.
 *
 * toOutput() est public pour être réutilisé par AlbumProcessor après persist.
 *
 * @implements ProviderInterface<AlbumOutput>
 */
final class AlbumProvider implements ProviderInterface
{
    public function __construct(
        private readonly AlbumRepository $repository,
        private readonly Pagination $pagination,
        private readonly Security $security,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var User|null $currentUser */
        $currentUser = $this->security->getUser();
        if ($currentUser === null) {
            throw new AccessDeniedHttpException();
        }

        if (isset($uriVariables['id'])) {
            $album = $this->repository->find($uriVariables['id']);
            if ($album === null) {
                return null;
            }

            if (!$album->isOwnedBy($currentUser)) {
                throw new AccessDeniedHttpException();
            }

            return $this->toOutput($album);
        }

        [$page, $offset, $limit] = $this->pagination->getPagination($operation, $context);
        $total = $this->repository->countByOwner($currentUser);
        $items = array_map($this->toOutput(...), $this->repository->findByOwner($currentUser, $limit, $offset));

        return new TraversablePaginator(new \ArrayIterator($items), $page, $limit, $total);
    }

    public function toOutput(Album $album): AlbumOutput
    {
        $output = new AlbumOutput();
        $output->id = (string) $album->getId();
        $output->name = $album->getName();
        $output->ownerId = (string) $album->getOwner()->getId();
        $output->mediaCount = $album->getMedias()->count();
        $output->createdAt = $album->getCreatedAt()->format(\DateTimeInterface::ATOM);

        return $output;
    }
}
