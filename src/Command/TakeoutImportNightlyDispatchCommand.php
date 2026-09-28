<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\TakeoutImportExtractMessage;
use App\Repository\TakeoutImportRepository;
use App\Service\Takeout\ServerLoadChecker;
use App\Service\Takeout\TakeoutImportTmpDirLocator;
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
 */
#[AsCommand(name: 'app:takeout:nightly-dispatch', description: 'Dispatche les imports Google Takeout dès que le serveur est calme')]
final class TakeoutImportNightlyDispatchCommand extends Command
{
    public function __construct(
        private readonly TakeoutImportRepository $repository,
        private readonly TakeoutImportTmpDirLocator $tmpDirLocator,
        private readonly MessageBusInterface $bus,
        private readonly ServerLoadChecker $loadChecker,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->loadChecker->isServerCalmEnough()) {
            $io->writeln('Serveur trop chargé, aucun import dispatché — retenté au prochain cycle.');

            return Command::SUCCESS;
        }

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
