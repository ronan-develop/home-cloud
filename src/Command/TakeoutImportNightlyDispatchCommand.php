<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\TakeoutImportExtractMessage;
use App\Repository\TakeoutImportRepository;
use App\Service\Takeout\TakeoutImportTmpDirLocator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Dispatche les imports Takeout "scheduled" (#522) — appelée par cron
 * nocturne, même fenêtre horaire que le déploiement différé (#421) et les
 * autres crons de maintenance. Un import volumineux se faisait tuer (SIGKILL)
 * par le LVE du mutualisé o2switch même en pleine journée sous forte charge,
 * indépendamment du découpage par ZIP (#520) — démarrer le traitement la
 * nuit, quand la charge partagée du serveur est généralement plus basse,
 * limite ce risque (recommandation documentée par o2switch pour tout
 * traitement lourd, cf. faq.o2switch.fr/cpanel/mesures/suivi-usage-ressources/).
 *
 * TakeoutImportStartController ne dispatche plus immédiatement : il marque
 * l'import "scheduled" et cette commande prend le relais ici, un ZIP à la
 * fois par import (même pattern que #520).
 */
#[AsCommand(name: 'app:takeout:nightly-dispatch', description: 'Dispatche les imports Google Takeout en attente du cycle nocturne')]
final class TakeoutImportNightlyDispatchCommand extends Command
{
    public function __construct(
        private readonly TakeoutImportRepository $repository,
        private readonly TakeoutImportTmpDirLocator $tmpDirLocator,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $scheduledImports = $this->repository->findAllScheduled();

        foreach ($scheduledImports as $import) {
            $zipPaths = $this->tmpDirLocator->zipPathsFor($import);
            $firstZipPath = array_shift($zipPaths);

            $this->bus->dispatch(new TakeoutImportExtractMessage((string) $import->getId(), $firstZipPath, $zipPaths));
        }

        $io->writeln(sprintf('%d import(s) Takeout dispatché(s).', count($scheduledImports)));

        return Command::SUCCESS;
    }
}
