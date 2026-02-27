<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\FileOutput;
use App\Repository\FileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Traite l'opération DELETE sur la ressource File.
 *
 * Rôle : supprime les métadonnées du fichier en base.
 * L'upload (POST) est géré par FileUploadController (multipart/form-data).
 *
 * Choix :
 * - Séparation POST/DELETE : API Platform ne supportant pas nativement
 *   multipart, le POST est délégué à un controller dédié (FileUploadController).
 *   Ce Processor ne gère donc que DELETE pour rester cohérent avec l'architecture.
 * - Le fichier physique n'est PAS supprimé — responsabilité du service de stockage.
 *
 * @implements ProcessorInterface<FileOutput, null>
 */
final class FileProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FileRepository $fileRepository,
    ) {}

    /**
     * DELETE /api/v1/files/{id} — supprime les métadonnées du fichier.
     * Retourne null → API Platform génère une réponse 204 No Content.
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $file = $this->fileRepository->find($uriVariables['id'])
            ?? throw new NotFoundHttpException('File not found');

        $this->em->remove($file);
        $this->em->flush();

        return null;
    }
}
