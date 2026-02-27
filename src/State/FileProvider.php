<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\FileOutput;
use App\Entity\File;
use App\Repository\FileRepository;
use App\Repository\FolderRepository;
use Symfony\Component\HttpFoundation\RequestStack;
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
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (isset($uriVariables['id'])) {
            $file = $this->repository->find($uriVariables['id']);

            // null → API Platform répond automatiquement 404
            return $file ? $this->toOutput($file) : null;
        }

        // Filtre optionnel par dossier : GET /api/v1/files?folderId=<uuid>
        $folderId = $this->requestStack->getCurrentRequest()?->query->get('folderId');

        if ($folderId !== null) {
            try {
                $folder = $this->folderRepository->find(Uuid::fromString($folderId));
            } catch (\InvalidArgumentException) {
                return [];
            }

            if ($folder === null) {
                return [];
            }

            return array_map($this->toOutput(...), $this->repository->findBy(['folder' => $folder]));
        }

        return array_map($this->toOutput(...), $this->repository->findAll());
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
