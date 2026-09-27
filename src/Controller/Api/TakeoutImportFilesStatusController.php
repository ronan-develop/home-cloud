<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Interface\Auth\OwnershipCheckerInterface;
use App\Repository\TakeoutImportRepository;
use App\Service\Takeout\ChunkedFileAssembler;
use App\Service\Takeout\TakeoutImportTmpDirLocator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * GET /api/v1/takeout-imports/{id}/files/status — liste les fichiers déjà
 * partiellement uploadés pour un import en attente, avec leur état de
 * reprise (#481). Le front compare `hashOfFirstChunk` au hash du premier
 * chunk du fichier qu'il s'apprête à envoyer : en cas de correspondance, il
 * reprend à `chunkIndex + 1` au lieu de tout renvoyer depuis 0.
 */
#[AsController]
final class TakeoutImportFilesStatusController extends AbstractController
{
    public function __construct(
        private readonly TakeoutImportRepository $takeoutImportRepository,
        private readonly OwnershipCheckerInterface $ownershipChecker,
        private readonly ChunkedFileAssembler $chunkedFileAssembler,
        private readonly TakeoutImportTmpDirLocator $tmpDirLocator,
    ) {}

    public function __invoke(string $id): Response
    {
        $import = $this->takeoutImportRepository->find($id);
        if ($import === null) {
            throw new NotFoundHttpException();
        }

        $this->ownershipChecker->denyUnlessOwner($import);

        $filePaths = $this->tmpDirLocator->zipPathsFor($import);

        $files = [];
        foreach ($filePaths as $filePath) {
            $state = $this->chunkedFileAssembler->resumeState($filePath);
            if ($state === null) {
                continue;
            }
            $files[] = [
                'filename' => basename($filePath),
                'chunkIndex' => $state['chunkIndex'],
                'hashOfFirstChunk' => $state['hashOfFirstChunk'],
            ];
        }

        return new JsonResponse($files, Response::HTTP_OK);
    }
}
