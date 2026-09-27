<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\TakeoutImport;
use App\Interface\Auth\OwnershipCheckerInterface;
use App\Repository\TakeoutImportRepository;
use App\Service\Takeout\ChunkedFileAssembler;
use App\Service\Takeout\TakeoutImportTmpDirLocator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * POST /api/v1/takeout-imports/{id}/files — ajoute une tranche d'un ZIP à un
 * import en attente (#466). Un ZIP Google Photos Takeout peut dépasser 512M
 * (plafond dur de l'hébergement mutualisé o2switch, confirmé non
 * contournable via .user.ini) — le front découpe chaque fichier en tranches
 * envoyées séquentiellement (multipart : "file" le contenu binaire du
 * chunk, "chunkIndex"/"totalChunks" sa position), réassemblées ici via
 * ChunkedFileAssembler. Un fichier avec un seul chunk (totalChunks=1) reste
 * le cas trivial d'un envoi non découpé.
 */
#[AsController]
final class TakeoutImportFileUploadController extends AbstractController
{
    public function __construct(
        private readonly TakeoutImportRepository $takeoutImportRepository,
        private readonly OwnershipCheckerInterface $ownershipChecker,
        private readonly ChunkedFileAssembler $chunkedFileAssembler,
        private readonly TakeoutImportTmpDirLocator $tmpDirLocator,
    ) {}

    public function __invoke(string $id, Request $request): Response
    {
        $import = $this->findPendingImportOrFail($id);

        $uploadedChunk = $request->files->get('file');
        if ($uploadedChunk === null) {
            throw new BadRequestHttpException('A file chunk must be uploaded (multipart field: "file")');
        }

        $filename = (string) $request->request->get('filename', $uploadedChunk->getClientOriginalName());
        $chunkIndex = (int) $request->request->get('chunkIndex', 0);
        $totalChunks = (int) $request->request->get('totalChunks', 1);

        $importTmpDir = $this->tmpDirLocator->dirFor($import);
        if (!is_dir($importTmpDir)) {
            mkdir($importTmpDir, 0777, true);
        }

        $targetPath = $importTmpDir . '/' . basename($filename);

        try {
            $this->chunkedFileAssembler->appendChunk(
                $targetPath,
                $chunkIndex,
                $totalChunks,
                (string) file_get_contents($uploadedChunk->getPathname()),
            );
        } catch (\RuntimeException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

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
