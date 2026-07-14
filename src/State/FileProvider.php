<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\FileOutput;
use App\Entity\File;
use App\Entity\User;
use App\Repository\FileRepository;
use App\Repository\FolderRepository;
use App\Security\ResourceAccessChecker;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Fournit les données lues pour les opérations GET sur la ressource File.
 *
 * Rôle : couche de lecture — transforme les entités Doctrine File en DTOs
 * FileOutput sans jamais exposer l'entité directement.
 *
 * Supporte le filtre ?folderId= sur la collection via QueryBuilder.
 * toOutput() est public pour être réutilisé par FileProcessor après persist.
 *
 * @implements ProviderInterface<FileOutput>
 */
final class FileProvider implements ProviderInterface
{
    public function __construct(
        private readonly FileRepository $repository,
        private readonly FolderRepository $folderRepository,
        private readonly RequestStack $requestStack,
        private readonly Pagination $pagination,
        private readonly Security $security,
        private readonly ResourceAccessChecker $resourceAccessChecker,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (isset($uriVariables['id'])) {
            $file = $this->repository->find($uriVariables['id']);
            if ($file === null) {
                return null;
            }

            /** @var User|null $currentUser */
            $currentUser = $this->security->getUser();
            if ($operation instanceof Get && $currentUser !== null
                && !$this->resourceAccessChecker->canRead($currentUser, 'file', $file->getId(), $file->getOwner())) {
                throw new AccessDeniedHttpException();
            }

            return $this->toOutput($file);
        }

        /** @var User|null $currentUser */
        $currentUser = $this->security->getUser();
        if ($currentUser === null) {
            throw new AccessDeniedHttpException();
        }

        $req      = $this->requestStack->getCurrentRequest();
        $folderId = $req?->query->get('folderId');
        $search   = $req?->query->get('originalName');
        $order    = $req?->query->all('order') ?? [];

        [$page, $offset, $limit] = $this->pagination->getPagination($operation, $context);

        if ($folderId !== null) {
            try {
                Uuid::fromString($folderId);
            } catch (\InvalidArgumentException) {
                return new TraversablePaginator(new \ArrayIterator([]), $page, $limit, 0);
            }
        }

        $total = $this->repository->countFiltered($currentUser, $search ?: null, $folderId);
        $items = array_map($this->toOutput(...), $this->repository->findFiltered($currentUser, $search ?: null, $folderId, $order, $limit, $offset));

        return new TraversablePaginator(new \ArrayIterator($items), $page, $limit, $total);
    }

    /**
     * Mappe une entité File vers son DTO de sortie.
     * Public car réutilisé par FileProcessor pour retourner le DTO après persist.
     */
    public function toOutput(File $file): FileOutput
    {
        $output = new FileOutput();
        $output->id = (string) $file->getId();
        $output->originalName = $file->getOriginalName();
        $output->mimeType = $file->getMimeType();
        $output->size = $file->getSize();
        $output->path = $file->getPath();
        $output->folderId = (string) $file->getFolder()->getId();
        $output->folderName = $file->getFolder()->getName();
        $output->ownerId = (string) $file->getOwner()->getId();
        $output->createdAt = $file->getCreatedAt()->format(\DateTimeInterface::ATOM);

        return $output;
    }
}
