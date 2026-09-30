<?php

declare(strict_types=1);

namespace App\Handler;

use App\Message\TakeoutImportExtractMessage;
use App\Message\TakeoutImportProcessMessage;
use App\Repository\TakeoutImportRepository;
use App\Service\Takeout\TakeoutZipExtractor;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Extrait UN SEUL ZIP par appel (#520) — sur un import volumineux (plusieurs
 * dizaines de Go dans un seul message), le worker Messenger était tué en
 * plein milieu de l'extraction par la contention LVE du mutualisé o2switch
 * (SIGKILL, aucune exception catchable côté PHP, constaté en conditions
 * réelles le 2026-09-28). Redispatche le ZIP suivant comme un nouveau
 * message tant qu'il en reste, laissant chaque cycle de worker se terminer
 * proprement ; une fois tous extraits, passe la main à
 * TakeoutImportProcessMessage pour la phase parsing/import.
 */
#[AsMessageHandler]
final class TakeoutImportExtractHandler
{
    public function __construct(
        private readonly TakeoutImportRepository $importRepository,
        private readonly TakeoutZipExtractor $zipExtractor,
        private readonly MessageBusInterface $bus,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public function __invoke(TakeoutImportExtractMessage $message): void
    {
        $import = $this->importRepository->find($message->takeoutImportId);
        if ($import === null) {
            $this->logger->warning('TakeoutImportExtractMessage : import introuvable', [
                'takeoutImportId' => $message->takeoutImportId,
            ]);

            return;
        }

        $workDir = sys_get_temp_dir() . '/takeout-import-' . $import->getId()->toRfc4122();

        try {
            // #515 : totalZipCount ne peut être déduit qu'au premier appel
            // (zipPath + remainingZipPaths = le lot complet) — les appels
            // suivants ne connaissent plus que ce qu'il reste, pas le total,
            // donc ne doivent jamais l'écraser.
            if ($import->getTotalZipCount() === null) {
                $import->markExtracting(count($message->remainingZipPaths) + 1);
            }

            $isZipFullyExtracted = $this->zipExtractor->extract($message->zipPath, $workDir);

            if (!$isZipFullyExtracted) {
                // Batch d'entrées limité atteint (#543) : le ZIP courant
                // n'est pas fini, on redispatche le MÊME zipPath — le
                // checkpoint .progress interne à TakeoutZipExtractor reprend
                // automatiquement à la bonne entrée au prochain appel.
                $this->bus->dispatch(new TakeoutImportExtractMessage($message->takeoutImportId, $message->zipPath, $message->remainingZipPaths));

                return;
            }

            $import->incrementExtractedZipCount();
            $this->em->flush();

            if ($message->remainingZipPaths === []) {
                $this->bus->dispatch(new TakeoutImportProcessMessage($message->takeoutImportId));

                return;
            }

            $remaining = $message->remainingZipPaths;
            $nextZipPath = array_shift($remaining);
            $this->bus->dispatch(new TakeoutImportExtractMessage($message->takeoutImportId, $nextZipPath, $remaining));
        } catch (\Throwable $e) {
            $this->logger->error('TakeoutImportExtractMessage : échec de l\'extraction', [
                'takeoutImportId' => $message->takeoutImportId,
                'zipPath' => $message->zipPath,
                'error' => $e->getMessage(),
            ]);

            $import->markFailed($e->getMessage(), 0, 0, 0);
            $this->em->flush();
        }
    }
}
