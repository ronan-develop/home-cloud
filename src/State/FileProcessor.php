<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\FileOutput;
use App\Interface\DefaultFolderServiceInterface;
use App\Repository\FileRepository;
use App\Repository\MediaRepository;
use App\Repository\UserRepository;
use App\Interface\StorageServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Psr\Log\LoggerInterface;

/**
 * Traite l'opération DELETE sur la ressource File.
 *
 * Rôle : supprime les métadonnées du fichier en base ET le fichier physique sur disque.
 * Si un Media est lié au File, son thumbnail est également supprimé du disque avant
 * que le CASCADE DB ne supprime la ligne Media.
 *
 * L'upload (POST) est géré par FileUploadController (multipart/form-data).
 *
 * Choix :
 * - Séparation POST/DELETE : API Platform ne supportant pas nativement
 *   multipart, le POST est délégué à un controller dédié (FileUploadController).
 *   Ce Processor ne gère donc que DELETE.
 *
 * @implements ProcessorInterface<FileOutput, null>
 */
final class FileProcessor implements ProcessorInterface
{
    /**
     * Pattern recommandé : injection du TokenStorageInterface et LoggerInterface pour obtenir l'utilisateur courant de façon fiable (test/prod).
     * Voir FolderProcessor pour l'implémentation complète.
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FileRepository $fileRepository,
        private readonly MediaRepository $mediaRepository,
        private readonly UserRepository $userRepository,
        private readonly StorageServiceInterface $storageService,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly LoggerInterface $logger,
        private readonly DefaultFolderServiceInterface $defaultFolderService,
    ) {}

    /**
     * Récupère l'utilisateur authentifié depuis le TokenStorage (pattern commun).
     */
    private function getAuthenticatedUser(): ?\App\Entity\User
    {
        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            $this->logger->warning('⚠️ No token in TokenStorage');
            return null;
        }
        $user = $token->getUser();
        if ($user instanceof \App\Entity\User) {
            return $user;
        }
        if (is_string($user) && filter_var($user, FILTER_VALIDATE_EMAIL)) {
            return $this->userRepository->findOneBy(['email' => $user]);
        }
        $this->logger->warning('⚠️ User type not recognized', [
            'type' => gettype($user),
            'value' => $user,
        ]);
        return null;
    }

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
     * PATCH /api/v1/files/{id} — déplace le fichier vers un autre dossier.
     */
    private function handlePatch(FileOutput $data, array $uriVariables): FileOutput
    {
        $file = $this->fileRepository->find($uriVariables['id'])
            ?? throw new NotFoundHttpException('File not found');

        $user = $this->getAuthenticatedUser();
        if ($user === null) {
            throw new \Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException('', 'Authentication required');
        }

        // Vérifie ownership du fichier
        if ((string)$file->getOwner()->getId() !== (string)$user->getId()) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('You do not own this file');
        }

        // targetFolderId null/vide → dossier "Uploads" par défaut
        if ($data->targetFolderId === null || $data->targetFolderId === '') {
            $targetFolder = $this->defaultFolderService->resolve(null, null, $user);
        } else {
            $targetFolder = $this->em->getRepository(\App\Entity\Folder::class)->find($data->targetFolderId);
            if ($targetFolder === null) {
                throw new NotFoundHttpException('Target folder not found');
            }
            if ((string)$targetFolder->getOwner()->getId() !== (string)$user->getId()) {
                throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('You do not own the target folder');
            }
        }

        // Déplacement effectif
        $file->setFolder($targetFolder);
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
