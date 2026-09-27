<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\TakeoutImport;
use App\Interface\Auth\OwnershipCheckerInterface;
use App\Repository\TakeoutImportRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * POST /api/v1/takeout-imports/{id}/files — ajoute un ZIP à un import en
 * attente (#466), un seul fichier par appel. Le front répète cet appel une
 * fois par ZIP sélectionné, pour rester sous la limite de taille de requête
 * du serveur mutualisé (413 constaté avec un seul gros POST multi-fichiers).
 */
#[AsController]
final class TakeoutImportFileUploadController extends AbstractController
{
    public function __construct(
        private readonly TakeoutImportRepository $takeoutImportRepository,
        private readonly OwnershipCheckerInterface $ownershipChecker,
        private readonly string $takeoutTmpDir,
    ) {}

    public function __invoke(string $id, Request $request): Response
    {
        $import = $this->findPendingImportOrFail($id);

        $uploadedFile = $request->files->get('file');
        if ($uploadedFile === null) {
            throw new BadRequestHttpException('A ZIP file must be uploaded (multipart field: "file")');
        }

        $importTmpDir = sprintf('%s/%s', $this->takeoutTmpDir, $import->getId()->toRfc4122());
        if (!is_dir($importTmpDir)) {
            mkdir($importTmpDir, 0777, true);
        }

        $filename = sprintf('%d-%s', count(glob($importTmpDir . '/*') ?: []), $uploadedFile->getClientOriginalName());
        $uploadedFile->move($importTmpDir, $filename);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function findPendingImportOrFail(string $id): TakeoutImport
    {
        $import = $this->takeoutImportRepository->find($id);
        if ($import === null) {
            throw new NotFoundHttpException();
        }

        $this->ownershipChecker->denyUnlessOwner($import);

        if ($import->getStatus() !== TakeoutImport::STATUS_PENDING) {
            throw new BadRequestHttpException('This import has already been started');
        }

        return $import;
    }
}
