<?php

declare(strict_types=1);

namespace App\Controller\Api\Takeout;

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
                // Fichier présent sur disque sans marqueur .progress : déjà
                // entièrement reçu (#507) — signalé complet plutôt qu'omis,
                // pour que le front sache qu'il n'a pas besoin de le renvoyer
                // et puisse refléter la complétude réelle dès la reprise.
                $files[] = [
                    'filename' => basename($filePath),
                    'chunkIndex' => null,
                    'hashOfFirstChunk' => null,
                    'complete' => true,
                ];
                continue;
            }
            $files[] = [
                'filename' => basename($filePath),
                'chunkIndex' => $state['chunkIndex'],
                'hashOfFirstChunk' => $state['hashOfFirstChunk'],
                'complete' => false,
            ];
        }

        return new JsonResponse($files, Response::HTTP_OK);
    }
}
