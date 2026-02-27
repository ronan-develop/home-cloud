<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\FileOutput;
use App\Repository\FileRepository;
use App\Repository\MediaRepository;
use App\Service\StorageService;
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
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FileRepository $fileRepository,
        private readonly MediaRepository $mediaRepository,
        private readonly StorageService $storageService,
    ) {}

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
