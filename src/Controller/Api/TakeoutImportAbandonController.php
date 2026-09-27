<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\TakeoutImport;
use App\Interface\Auth\OwnershipCheckerInterface;
use App\Repository\TakeoutImportRepository;
use App\Service\Takeout\TakeoutImportTmpDirLocator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * DELETE /api/v1/takeout-imports/{id} — abandonne un import Takeout en
 * attente (#481). Supprime immédiatement les fichiers déjà uploadés côté
 * disque et la ligne en base, sans attendre la purge automatique à 7 jours
 * (#493) — action explicite demandée par l'utilisateur depuis la liste des
 * imports en attente.
 */
#[AsController]
final class TakeoutImportAbandonController extends AbstractController
{
    public function __construct(
        private readonly TakeoutImportRepository $takeoutImportRepository,
        private readonly OwnershipCheckerInterface $ownershipChecker,
        private readonly TakeoutImportTmpDirLocator $tmpDirLocator,
    ) {}

    public function __invoke(string $id): Response
    {
        $import = $this->takeoutImportRepository->find($id);
        if ($import === null) {
            throw new NotFoundHttpException();
        }

        $this->ownershipChecker->denyUnlessOwner($import);

        if ($import->getStatus() !== TakeoutImport::STATUS_PENDING) {
            throw new BadRequestHttpException('Only a pending import can be abandoned');
        }

        $this->deleteTmpDir($import);
        $this->takeoutImportRepository->remove($import);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function deleteTmpDir(TakeoutImport $import): void
    {
        $importTmpDir = $this->tmpDirLocator->dirFor($import);
        if (!is_dir($importTmpDir)) {
            return;
        }

        array_map('unlink', glob($importTmpDir . '/*') ?: []);
        rmdir($importTmpDir);
    }
}
