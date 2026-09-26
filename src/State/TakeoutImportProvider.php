<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\TakeoutImportOutput;
use App\Entity\TakeoutImport;
use App\Entity\User;
use App\Repository\TakeoutImportRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Fournit les données lues pour GET /v1/takeout-imports/{id} (#327).
 *
 * @implements ProviderInterface<TakeoutImportOutput>
 */
final class TakeoutImportProvider implements ProviderInterface
{
    public function __construct(
        private readonly TakeoutImportRepository $repository,
        private readonly Security $security,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $import = $this->repository->find($uriVariables['id'] ?? null);
        if ($import === null) {
            throw new NotFoundHttpException();
        }

        /** @var User|null $currentUser */
        $currentUser = $this->security->getUser();
        if ($currentUser === null || (string) $import->getOwner()->getId() !== (string) $currentUser->getId()) {
            throw new AccessDeniedHttpException();
        }

        return $this->toOutput($import);
    }

    public function toOutput(TakeoutImport $import): TakeoutImportOutput
    {
        $output = new TakeoutImportOutput();
        $output->id = (string) $import->getId();
        $output->status = $import->getStatus();
        $output->mediaImportedCount = $import->getMediaImportedCount();
        $output->duplicatesSkippedCount = $import->getDuplicatesSkippedCount();
        $output->unrecognizedFilesCount = $import->getUnrecognizedFilesCount();
        $output->totalMediaCount = $import->getTotalMediaCount();
        $output->processedCount = $import->getProcessedCount();
        $output->createdAt = $import->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $output->completedAt = $import->getCompletedAt()?->format(\DateTimeInterface::ATOM);
        $output->errorMessage = $import->getErrorMessage();

        return $output;
    }
}
