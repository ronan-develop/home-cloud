<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AlbumOutput;
use App\Entity\Album;
use App\Repository\AlbumRepository;

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
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (isset($uriVariables['id'])) {
            $album = $this->repository->find($uriVariables['id']);

            return $album ? $this->toOutput($album) : null;
        }

        [$page, $offset, $limit] = $this->pagination->getPagination($operation, $context);
        $total = $this->repository->count([]);
        $items = array_map($this->toOutput(...), $this->repository->findBy([], [], $limit, $offset));

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
