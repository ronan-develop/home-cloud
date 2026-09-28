<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\TakeoutImport;
use App\Interface\Auth\OwnershipCheckerInterface;
use App\Repository\TakeoutImportRepository;
use App\Service\Takeout\TakeoutImportTmpDirLocator;
use App\State\TakeoutImportProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * POST /api/v1/takeout-imports/{id}/start — marque l'import "scheduled" une
 * fois tous les ZIP envoyés via TakeoutImportFileUploadController (#466).
 *
 * #522 : ne dispatche plus immédiatement le traitement — un import
 * volumineux se faisait tuer (SIGKILL) par le LVE du mutualisé o2switch même
 * en pleine journée sous forte charge, indépendamment du découpage par ZIP
 * (#520). Le dispatch réel est fait par TakeoutImportNightlyDispatchCommand
 * (cron nocturne), quand la charge partagée du serveur est généralement plus
 * basse.
 */
#[AsController]
final class TakeoutImportStartController extends AbstractController
{
    public function __construct(
        private readonly TakeoutImportRepository $takeoutImportRepository,
        private readonly OwnershipCheckerInterface $ownershipChecker,
        private readonly TakeoutImportProvider $provider,
        private readonly SerializerInterface $serializer,
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
            throw new BadRequestHttpException('This import has already been started');
        }

        $zipPaths = $this->tmpDirLocator->zipPathsFor($import);
        if ($zipPaths === []) {
            throw new BadRequestHttpException('At least one ZIP file must be uploaded before starting the import');
        }

        $import->markScheduled();
        $this->takeoutImportRepository->save($import);

        $output = $this->provider->toOutput($import);

        return new JsonResponse(
            json_decode($this->serializer->serialize($output, 'json'), true),
            Response::HTTP_ACCEPTED,
        );
    }
}
