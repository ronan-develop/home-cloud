<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\TakeoutDispatchLog;
use App\Message\TakeoutImportExtractMessage;
use App\Repository\TakeoutDispatchLogRepository;
use App\Repository\TakeoutImportRepository;
use App\Service\Takeout\ServerLoadChecker;
use App\Service\Takeout\TakeoutImportTmpDirLocator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Dispatche les imports Takeout "scheduled" (#522) dès que le serveur est
 * calme (#524) — appelée toutes les 15 min, pas seulement la nuit. Un import
 * volumineux se faisait tuer (SIGKILL) par le LVE du mutualisé o2switch même
 * en pleine journée sous forte charge, indépendamment du découpage par ZIP
 * (#520). Comportement dynamique, pas une bascule jour/nuit binaire :
 * ServerLoadChecker (seuil calibré empiriquement à charge ~10 observée lors
 * d'un kill réel) décide à chaque exécution si le moment est propice — sinon
 * l'import reste "scheduled" et sera retenté au cycle suivant, de jour comme
 * de nuit.
 *
 * TakeoutImportStartController ne dispatche plus immédiatement : il marque
 * l'import "scheduled" et cette commande prend le relais ici, un ZIP à la
 * fois par import (même pattern que #520).
 *
 * #528 : chaque cycle (calme, chargé, ou rien en attente) est tracé dans
 * TakeoutDispatchLog puis les lignes de plus de 30 jours sont purgées. Pure
 * observation : un échec d'écriture est journalisé mais ne bloque jamais le
 * dispatch ni ne fait échouer la commande.
 */
#[AsCommand(name: 'app:takeout:nightly-dispatch', description: 'Dispatche les imports Google Takeout dès que le serveur est calme')]
final class TakeoutImportNightlyDispatchCommand extends Command
{
    public function __construct(
        private readonly TakeoutImportRepository $repository,
        private readonly TakeoutImportTmpDirLocator $tmpDirLocator,
        private readonly MessageBusInterface $bus,
        private readonly ServerLoadChecker $loadChecker,
        private readonly TakeoutDispatchLogRepository $dispatchLogRepository,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Une seule mesure par cycle : l'issue loguée reste cohérente avec la charge loguée.
        $loadAverage = $this->loadChecker->getLoadAverage();

        if (!$this->loadChecker->isCalm($loadAverage)) {
            $io->writeln('Serveur trop chargé, aucun import dispatché — retenté au prochain cycle.');
            $this->recordCycle(TakeoutDispatchLog::OUTCOME_DEFERRED, $loadAverage, $this->repository->countScheduled(), 0);
            $this->purgeOldLogs();

            return Command::SUCCESS;
        }

        $scheduledImports = $this->repository->findAllScheduled();

        foreach ($scheduledImports as $import) {
            $zipPaths = $this->tmpDirLocator->zipPathsFor($import);
            $firstZipPath = array_shift($zipPaths);

            $this->bus->dispatch(new TakeoutImportExtractMessage((string) $import->getId(), $firstZipPath, $zipPaths));
        }

        $count = count($scheduledImports);
        $io->writeln(sprintf('%d import(s) Takeout dispatché(s).', $count));
        $this->recordCycle($count > 0 ? TakeoutDispatchLog::OUTCOME_DISPATCHED : TakeoutDispatchLog::OUTCOME_IDLE, $loadAverage, $count, $count);
        $this->purgeOldLogs();

        return Command::SUCCESS;
    }

    /**
     * @param array{0: float, 1: float, 2: float}|null $loadAverage
     */
    private function recordCycle(string $outcome, ?array $loadAverage, int $scheduledCount, int $dispatchedCount): void
    {
        try {
            $this->dispatchLogRepository->save(new TakeoutDispatchLog(
                $outcome,
                $loadAverage,
                $this->loadChecker->getThreshold(),
                $scheduledCount,
                $dispatchedCount,
            ));
        } catch (\Throwable $e) {
            $this->logger->error('TakeoutImportNightlyDispatchCommand : échec de l\'écriture de l\'historique de dispatch', ['exception' => $e]);
        }
    }

    private function purgeOldLogs(): void
    {
        try {
            $this->dispatchLogRepository->purgeOlderThan(new \DateTimeImmutable('-' . TakeoutDispatchLog::PURGE_AFTER_DAYS . ' days'));
        } catch (\Throwable $e) {
            $this->logger->error('TakeoutImportNightlyDispatchCommand : échec de la purge de l\'historique de dispatch', ['exception' => $e]);
        }
    }
}
