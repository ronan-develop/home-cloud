<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\FileRepository;
use App\Service\StorageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller dédié au téléchargement des fichiers uploadés.
 *
 * Rôle : streamer le fichier binaire depuis le disque avec les headers appropriés
 * (Content-Type, Content-Disposition).
 *
 * Pourquoi un controller séparé et non une opération API Platform ?
 * API Platform sérialise en JSON. Pour streamer un binaire arbitraire,
 * on doit retourner une BinaryFileResponse hors du cycle de sérialisation.
 */
final class FileDownloadController extends AbstractController
{
    public function __construct(
        private readonly FileRepository $fileRepository,
        private readonly StorageService $storageService,
    ) {}

    #[Route('/api/v1/files/{id}/download', name: 'file_download', methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        $file = $this->fileRepository->find($id)
            ?? throw new NotFoundHttpException('File not found');

        $absolutePath = $this->storageService->getAbsolutePath($file->getPath());

        if (!file_exists($absolutePath)) {
            throw new NotFoundHttpException('Physical file not found');
        }

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $file->getOriginalName(),
        );

        return new Response(
            file_get_contents($absolutePath),
            Response::HTTP_OK,
            [
                'Content-Type' => $file->getMimeType(),
                'Content-Disposition' => $disposition,
            ],
        );
    }
}
