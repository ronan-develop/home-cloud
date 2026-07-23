<?php

declare(strict_types=1);

namespace App\Command;

use App\Interface\BroadcastOrchestratorInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Diffuse un message admin (#283) à tous les utilisateurs de toutes les
 * instances, ou d'une seule instance ciblée. Déclenchée manuellement ou par
 * l'interface admin web (les deux délèguent à BroadcastOrchestratorInterface).
 */
#[AsCommand(name: 'app:broadcast:send', description: 'Diffuse un message admin à tous les utilisateurs des instances déployées')]
final class BroadcastSendCommand extends Command
{
    public function __construct(
        private readonly BroadcastOrchestratorInterface $orchestrator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('subject', null, InputOption::VALUE_REQUIRED, 'Sujet du message')
            ->addOption('body', null, InputOption::VALUE_REQUIRED, 'Corps du message (HTML autorisé)')
            ->addOption('instance', null, InputOption::VALUE_REQUIRED, 'Cibler une seule instance (défaut : toutes)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, "Simuler sans envoyer d'email réel");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $results = $this->orchestrator->dispatch(
            (string) $input->getOption('subject'),
            (string) $input->getOption('body'),
            $input->getOption('instance'),
            (bool) $input->getOption('dry-run'),
        );

        foreach ($results as $instance => $success) {
            if ($success) {
                $io->writeln(sprintf('<info>%s</info> : OK', $instance));
            } else {
                $io->writeln(sprintf('<error>%s</error> : échec', $instance));
            }
        }

        return Command::SUCCESS;
    }
}
