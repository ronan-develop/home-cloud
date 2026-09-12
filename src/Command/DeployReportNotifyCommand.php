<?php

declare(strict_types=1);

namespace App\Command;

use App\Interface\DeployNotificationMailerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Parse le fichier de rapport écrit par bin/deploy-nightly.sh sur chaque
 * instance (#421, une ligne par instance : "<prenom>|<statut>|<étape>|<sha>"),
 * envoie le récapitulatif par email puis supprime le fichier. Appelée par le
 * dernier cron de la fenêtre nocturne, une fois toutes les instances passées.
 */
#[AsCommand(name: 'app:deploy-queue:notify', description: 'Envoie le rapport de déploiement nocturne et supprime le fichier source')]
final class DeployReportNotifyCommand extends Command
{
    public function __construct(
        private readonly DeployNotificationMailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('file', null, InputOption::VALUE_REQUIRED, 'Chemin du fichier de rapport à traiter');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getOption('file');

        if (!is_file($filePath)) {
            $this->logger->warning('DeployReportNotifyCommand : fichier de rapport absent, aucun cron n\'a tourné cette nuit', ['file' => $filePath]);
            $io->writeln('Aucun fichier de rapport trouvé — rien à envoyer.');

            return Command::SUCCESS;
        }

        $results = $this->parseReportFile($filePath);
        $this->mailer->sendDeployReport($results);
        unlink($filePath);

        $io->writeln(sprintf('Rapport envoyé pour %d instance(s).', count($results)));

        return Command::SUCCESS;
    }

    /**
     * @return list<array{instance: string, status: 'ok'|'failed'|'skipped', step: ?string, sha: ?string}>
     */
    private function parseReportFile(string $filePath): array
    {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $results = [];

        foreach ($lines as $line) {
            $parts = explode('|', $line);
            if (count($parts) !== 4) {
                $this->logger->warning('DeployReportNotifyCommand : ligne de rapport malformée ignorée', ['line' => $line]);
                continue;
            }

            [$instance, $status, $step, $sha] = $parts;

            $results[] = [
                'instance' => $instance,
                'status'   => $status,
                'step'     => $step === '-' ? null : $step,
                'sha'      => $sha === '-' ? null : $sha,
            ];
        }

        return $results;
    }
}
