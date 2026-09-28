<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\TakeoutImport;
use App\Interface\Auth\OwnershipCheckerInterface;
use App\Message\TakeoutImportExtractMessage;
use App\Repository\TakeoutImportRepository;
use App\Service\Takeout\TakeoutImportTmpDirLocator;
use App\State\TakeoutImportProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * POST /api/v1/takeout-imports/{id}/start — dispatche le traitement
 * asynchrone une fois tous les ZIP envoyés via
 * TakeoutImportFileUploadController (#466).
 */
#[AsController]
final class TakeoutImportStartController extends AbstractController
{
    public function __construct(
        private readonly TakeoutImportRepository $takeoutImportRepository,
        private readonly OwnershipCheckerInterface $ownershipChecker,
        private readonly TakeoutImportProvider $provider,
        private readonly SerializerInterface $serializer,
        private readonly MessageBusInterface $bus,
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

        // #520 : un ZIP à la fois (TakeoutImportExtractHandler redispatche
        // le reste) plutôt que tous les ZIP dans un seul message — un import
        // volumineux dépassait la contention LVE du mutualisé o2switch en
        // extrayant tout d'un coup dans un seul cycle de worker.
        $firstZipPath = array_shift($zipPaths);
        $this->bus->dispatch(new TakeoutImportExtractMessage((string) $import->getId(), $firstZipPath, $zipPaths));

        $output = $this->provider->toOutput($import);

        return new JsonResponse(
            json_decode($this->serializer->serialize($output, 'json'), true),
            Response::HTTP_ACCEPTED,
        );
    }
}
