use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\FileOutput;
use App\Repository\FileRepository;
use App\Repository\MediaRepository;
use App\Interface\StorageServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        private readonly StorageServiceInterface $storageService,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Récupère l'utilisateur authentifié depuis le TokenStorage (pattern commun).
     */
    private function getAuthenticatedUser(): ?\App\Entity\User
    {
        $token = $this->tokenStorage->getToken();
        $this->logger->info('🔍 TokenStorage State', [
            'has_token' => $token !== null,
            'token_class' => $token ? get_class($token) : 'null',
        ]);
        if ($token === null) {
            $this->logger->warning('⚠️ No token in TokenStorage');
            return null;
        }
        $user = $token->getUser();
        $this->logger->info('🔍 User from Token', [
            'user_class' => $user ? get_class($user) : 'null',
            'is_user_instance' => $user instanceof \App\Entity\User,
        ]);
        if ($user instanceof \App\Entity\User) {
            return $user;
        }
        if (is_string($user) && filter_var($user, FILTER_VALIDATE_EMAIL)) {
            $this->logger->info('🔍 User is string, searching by email', ['email' => $user]);
            return $this->fileRepository->findOneBy(['email' => $user]); // Adapter selon besoin
        }
        $this->logger->warning('⚠️ User type not recognized', [
            'type' => gettype($user),
            'value' => $user,
        ]);
        return null;
    }

    /**
     * DELETE /api/v1/files/{id} — supprime les métadonnées, le thumbnail ET le fichier physique.
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
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
}
