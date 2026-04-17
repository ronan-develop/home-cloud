<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\FolderOutput;
use App\Entity\Folder;
use App\Repository\FolderRepository;
use App\Security\AuthenticationResolver;

/**
 * Fournit les données lues pour les opérations GET sur la ressource Folder.
 *
 * Rôle : couche de lecture — transforme les entités Doctrine en DTOs FolderOutput
 * exposés par l'API, sans jamais exposer l'entité directement.
 *
 * Choix : séparation Provider / Processor pour respecter le principe de
 * responsabilité unique et le pattern CQRS (lecture ≠ écriture).
 * toOutput() est public pour être réutilisé par FolderProcessor après persist.
 *
 * @implements ProviderInterface<FolderOutput>
 */
final class FolderProvider implements ProviderInterface
{
    public function __construct(
        private readonly FolderRepository $repository,
        private readonly Pagination $pagination,
        private readonly AuthenticationResolver $authResolver,
    ) {}

    /**
     * Fournit un FolderOutput unique (GET /v1/folders/{id})
     * ou un tableau de FolderOutput (GET /v1/folders).
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (isset($uriVariables['id'])) {
            $folder = $this->repository->find($uriVariables['id']);

            // null → API Platform répond automatiquement 404
            return $folder ? $this->toOutput($folder) : null;
        }

        [$page, $offset, $limit] = $this->pagination->getPagination($operation, $context);

        $user = $this->authResolver->getAuthenticatedUser();
        if ($user === null) {
            return new TraversablePaginator(new \ArrayIterator([]), $page, $limit, 0);
        }

        $request = $context['request'] ?? null;
        $search  = $request?->query->get('name');
        $order   = $request?->query->all('order') ?? [];

        $total = $this->repository->countFiltered($user, $search ?: null);
        $items = array_map($this->toOutput(...), $this->repository->findFiltered($user, $search ?: null, $order, $limit, $offset));

        return new TraversablePaginator(new \ArrayIterator($items), $page, $limit, $total);
    }

    /**
     * Mappe une entité Folder vers son DTO de sortie.
     * Public car réutilisé par FolderProcessor pour retourner le DTO après écriture.
     */
    public function toOutput(Folder $folder): FolderOutput
    {
        $output = new FolderOutput();
        $output->id        = (string) $folder->getId();
        $output->name      = $folder->getName();
        $output->parentId  = $folder->getParent() ? (string) $folder->getParent()->getId() : null;
        $output->ownerId   = (string) $folder->getOwner()->getId();
        $output->mediaType = $folder->getMediaType()->value;
        $output->createdAt = $folder->getCreatedAt()->format(\DateTimeInterface::ATOM);

        return $output;
    }
}
