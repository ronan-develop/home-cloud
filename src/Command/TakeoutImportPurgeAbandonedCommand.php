<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\TakeoutImport;
use App\Repository\TakeoutImportRepository;
use App\Service\Takeout\TakeoutImportAbandoner;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Purge les imports Takeout pending abandonnés depuis plus de
 * TakeoutImport::PURGE_AFTER_DAYS jours (cf. #493).
 *
 * Un import jamais repris ni démarré reste orphelin indéfiniment sans ce
 * cron : à la fois la ligne en base et le dossier var/takeout-tmp/<uuid>/
 * (potentiellement plusieurs Go). À exécuter périodiquement via crontab
 * (cf. project_cron_messenger), même pattern que
 * ShareLinkPurgeRevokedCommand (#244).
 */
#[AsCommand(name: 'app:takeout:purge-abandoned', description: 'Purge les imports Google Photos Takeout pending abandonnés depuis plus de 7 jours')]
final class TakeoutImportPurgeAbandonedCommand extends Command
{
    public function __construct(
        private readonly TakeoutImportRepository $repository,
        private readonly TakeoutImportAbandoner $abandoner,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $threshold = (new \DateTimeImmutable())->modify('-' . TakeoutImport::PURGE_AFTER_DAYS . ' days');
        $abandonedImports = $this->repository->findPendingOlderThan($threshold);

        $purged = 0;

        // #504 : sans catch, une exception levée au milieu du lot (ex.
        // suppression disque/base échouée sur le Nième import) remonte à
        // Symfony Console, stack trace complète dans les logs crontab. Le
        // travail déjà accompli avant l'exception n'est pas perdu — seule
        // l'erreur est catchée et loggée, pas de rollback.
        try {
            foreach ($abandonedImports as $import) {
                $this->abandoner->abandon($import);
                ++$purged;
            }

            $io->writeln(sprintf('%d import(s) Takeout abandonné(s) purgé(s).', $purged));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->logger->error('TakeoutImportPurgeAbandonedCommand : échec de la purge', ['exception' => $e]);
            $io->writeln(sprintf('%d import(s) purgé(s) avant l\'échec.', $purged));
            $io->error('La purge des imports Takeout abandonnés a échoué.');

            return Command::FAILURE;
        }
    }
}
