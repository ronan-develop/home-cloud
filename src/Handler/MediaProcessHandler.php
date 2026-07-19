<?php

declare(strict_types=1);

namespace App\Handler;

use App\Entity\UploadBatch;
use App\Interface\BatchCompletionNotifierInterface;
use App\Interface\MediaProcessorInterface;
use App\Interface\UploadBatchRepositoryInterface;
use App\Message\MediaProcessMessage;
use App\Repository\FileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler Messenger qui traite le message MediaProcessMessage.
 *
 * Rôle : point d'entrée asynchrone (transport doctrine) du traitement média —
 * ne bloque pas la réponse HTTP de l'upload. La logique métier (EXIF,
 * thumbnail, idempotence) est déléguée à MediaProcessor.
 *
 * Depuis le routage par lot (#259), un message n'arrive ici que pour les lots
 * lourds (deferred). Une fois le fichier traité, si c'est le dernier du lot,
 * le handler marque le lot terminé et déclenche la notification (#260) — une
 * seule fois, gardé par notifiedAt (le worker peut rejouer un message).
 */
#[AsMessageHandler]
final class MediaProcessHandler
{
    public function __construct(
        private readonly FileRepository $fileRepository,
        private readonly MediaProcessorInterface $mediaProcessor,
        private readonly UploadBatchRepositoryInterface $uploadBatchRepository,
        private readonly BatchCompletionNotifierInterface $batchCompletionNotifier,
        private readonly EntityManagerInterface $em,
    ) {}

    public function __invoke(MediaProcessMessage $message): void
    {
        $file = $this->fileRepository->find($message->fileId);
        if ($file === null) {
            return;
        }

        $this->mediaProcessor->process($file);

        $this->notifyIfBatchCompleted($file->getBatch());
    }

    /**
     * Marque le lot terminé et notifie le propriétaire dès que tous ses
     * fichiers ont un Media. Ne concerne que les lots deferred (les lots
     * immediate ne passent pas par le worker), et n'agit qu'une fois
     * (notifiedAt). Le comptage s'appuie sur l'état réel en base
     * (countProcessed) plutôt qu'un compteur, donc reste correct si un message
     * est rejoué.
     */
    private function notifyIfBatchCompleted(?UploadBatch $batch): void
    {
        if ($batch === null || !$batch->isDeferred() || $batch->getNotifiedAt() !== null) {
            return;
        }

        if ($this->uploadBatchRepository->countProcessed($batch) < $batch->getExpectedCount()) {
            return;
        }

        $batch->setStatus(UploadBatch::STATUS_COMPLETED);
        $batch->setCompletedAt(new \DateTimeImmutable());
        $this->batchCompletionNotifier->notify($batch);

        $this->em->flush();
    }
}
