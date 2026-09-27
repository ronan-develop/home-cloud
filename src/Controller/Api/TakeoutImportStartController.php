<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\TakeoutImport;
use App\Interface\Auth\OwnershipCheckerInterface;
use App\Message\TakeoutImportMessage;
use App\Repository\TakeoutImportRepository;
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
        private readonly string $takeoutTmpDir,
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

        $importTmpDir = sprintf('%s/%s', $this->takeoutTmpDir, $import->getId()->toRfc4122());
        $zipPaths = glob($importTmpDir . '/*.zip') ?: [];
        if ($zipPaths === []) {
            throw new BadRequestHttpException('At least one ZIP file must be uploaded before starting the import');
        }

        $this->bus->dispatch(new TakeoutImportMessage((string) $import->getId(), $zipPaths));

        $output = $this->provider->toOutput($import);

        return new JsonResponse(
            json_decode($this->serializer->serialize($output, 'json'), true),
            Response::HTTP_ACCEPTED,
        );
    }
}
