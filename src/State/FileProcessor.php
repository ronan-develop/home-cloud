<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\FileOutput;
use App\Interface\DefaultFolderServiceInterface;
use App\Interface\OwnershipCheckerInterface;
use App\Repository\FileRepository;
use App\Repository\MediaRepository;
use App\Security\AuthenticationResolver;
use App\Service\FileActionService;
use App\Service\IriExtractor;
use App\Interface\StorageServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Traite l'opération DELETE et PATCH sur la ressource File.
 *
 * Rôle : dispatcher vers FileActionService (rename, move, delete).
 * DeleteHandler : supprime les métadonnées du fichier en base ET le fichier physique sur disque.
 * Si un Media est lié au File, son thumbnail est également supprimé du disque avant
 * que le CASCADE DB ne supprime la ligne Media.
 *
 * L'upload (POST) est géré par FileUploadController (multipart/form-data).
 *
 * @implements ProcessorInterface<FileOutput, null>
 */
final class FileProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FileRepository $fileRepository,
        private readonly MediaRepository $mediaRepository,
        private readonly StorageServiceInterface $storageService,
        private readonly AuthenticationResolver $authResolver,
        private readonly OwnershipCheckerInterface $ownershipChecker,
        private readonly DefaultFolderServiceInterface $defaultFolderService,
        private readonly FileActionService $fileActionService,
        private readonly RequestStack $requestStack,
        private readonly IriExtractor $iriExtractor,
    ) {}

    /**
     * Dispatcher pour DELETE et PATCH sur File.
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        return match (true) {
            $operation instanceof Delete => $this->handleDelete($uriVariables),
            $operation instanceof Patch  => $this->handlePatch($data, $uriVariables),
            default => throw new \LogicException('Unsupported operation for FileProcessor'),
        };
    }

    /**
     * DELETE /api/v1/files/{id} — supprime les métadonnées, le thumbnail ET le fichier physique.
     */
    private function handleDelete(array $uriVariables): null
    {
        $file = $this->fileRepository->find($uriVariables['id'])
            ?? throw new NotFoundHttpException('File not found');

        $this->ownershipChecker->denyUnlessOwner($file);

        // Supprimer le thumbnail du disque AVANT le cascade DB (onDelete: CASCADE supprime la ligne Media)
        $media = $this->mediaRepository->findOneBy(['file' => $file]);
        if ($media !== null && $media->getThumbnailPath() !== null) {
            $this->storageService->delete($media->getThumbnailPath());
        }

        $this->storageService->delete($file->getPath());
        $this->em->remove($file);
        $this->em->flush();

        return null;
    }

    /**
     * PATCH /api/v1/files/{id} — renomme ou déplace le fichier via FileActionService.
     */
    private function handlePatch(FileOutput $data, array $uriVariables): FileOutput
    {
        $file = $this->fileRepository->find($uriVariables['id'])
            ?? throw new NotFoundHttpException('File not found');

        $user = $this->authResolver->requireUser();

        // Déléguez le renommage à FileActionService (validation)
        if ($data->originalName !== '') {
            $this->fileActionService->rename($file, $data->originalName);
        }

        // Déléguez le déplacement à FileActionService (validation, ownership check)
        $body = json_decode($this->requestStack->getCurrentRequest()?->getContent() ?? '{}', true);
        if (is_array($body) && array_key_exists('targetFolderId', $body)) {
            $targetFolderId = $body['targetFolderId'];
            
            // Résoudre le dossier cible
            if ($targetFolderId === null || $targetFolderId === '') {
                // Aucun dossier fourni : résoudre au dossier par défaut (Uploads)
                $targetFolder = $this->defaultFolderService->resolve(null, null, $user);
            } else {
                // Extraire l'UUID depuis l'IRI si nécessaire
                $targetFolderId = $this->iriExtractor->extractUuid($targetFolderId);
                $targetFolder = $this->em->getRepository(\App\Entity\Folder::class)->find($targetFolderId)
                    ?? throw new NotFoundHttpException('Target folder not found');
            }

            $this->fileActionService->move($file, $targetFolder, $user);
        }

        $this->em->flush();

        // Retourne le DTO mis à jour
        $output = new FileOutput();
        $output->id = (string)$file->getId();
        $output->originalName = $file->getOriginalName();
        $output->mimeType = $file->getMimeType();
        $output->size = $file->getSize();
        $output->path = $file->getPath();
        $output->folderId = (string)$file->getFolder()->getId();
        $output->folderName = $file->getFolder()->getName();
        $output->ownerId = (string)$file->getOwner()->getId();
        $output->createdAt = $file->getCreatedAt()->format('Y-m-d H:i:s');
        return $output;
    }
}

