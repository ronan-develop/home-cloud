<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Interface\MediaProcessorInterface;
use App\Repository\FileRepository;
use App\Service\PendingMediaProcessingCollector;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Uid\Uuid;

/**
 * Traite les médias tout juste uploadés une fois la réponse HTTP envoyée.
 *
 * kernel.terminate se déclenche après que le client a déjà reçu la réponse
 * (rien n'ajoute de latence perçue) : c'est là qu'on génère la vignette et
 * qu'on extrait l'EXIF, au lieu d'attendre le prochain cycle du worker
 * Messenger, potentiellement plusieurs minutes plus tard selon la charge du
 * cron (cf. #251).
 *
 * MediaProcessor::process() est idempotent : si le worker traite quand même
 * ce même fichier plus tard (secours en cas d'échec de ce listener), il ne
 * fait rien de plus qu'un no-op.
 */
#[AsEventListener(event: KernelEvents::TERMINATE)]
final class ProcessPendingMediaListener
{
    public function __construct(
        private readonly PendingMediaProcessingCollector $collector,
        #[Autowire(lazy: true)]
        private readonly FileRepository $fileRepository,
        #[Autowire(lazy: true)]
        private readonly MediaProcessorInterface $mediaProcessor,
    ) {}

    public function __invoke(TerminateEvent $event): void
    {
        $fileIds = $this->collector->drain();
        if ($fileIds === []) {
            // Aucun upload média dans cette requête : ne pas instancier
            // FileRepository/MediaProcessor (et leurs dépendances, dont
            // StorageService) pour rien sur chaque requête de l'application.
            return;
        }

        foreach ($fileIds as $fileId) {
            $file = $this->fileRepository->find(Uuid::fromString($fileId));
            if ($file === null) {
                continue;
            }

            $this->mediaProcessor->process($file);
        }
    }
}
