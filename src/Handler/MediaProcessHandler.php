<?php

declare(strict_types=1);

namespace App\Handler;

use App\Interface\MediaProcessorInterface;
use App\Message\MediaProcessMessage;
use App\Repository\FileRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler Messenger qui traite le message MediaProcessMessage.
 *
 * Rôle : point d'entrée asynchrone (transport doctrine) du traitement média —
 * ne bloque pas la réponse HTTP de l'upload. La logique métier (EXIF,
 * thumbnail, idempotence) est déléguée à MediaProcessor, réutilisé aussi en
 * synchrone par les flux qui ont besoin du Media immédiatement (ex: import
 * direct dans un album).
 */
#[AsMessageHandler]
final class MediaProcessHandler
{
    public function __construct(
        private readonly FileRepository $fileRepository,
        private readonly MediaProcessorInterface $mediaProcessor,
    ) {}

    public function __invoke(MediaProcessMessage $message): void
    {
        $file = $this->fileRepository->find($message->fileId);
        if ($file === null) {
            return;
        }

        $this->mediaProcessor->process($file);
    }
}
