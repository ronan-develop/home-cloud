<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ShareLink;
use App\Interface\ShareLinkRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Purge physiquement les ShareLink révoqués depuis plus de
 * ShareLink::PURGE_AFTER_DAYS jours (cf. #244).
 *
 * La fenêtre de grâce laisse à l'owner le temps de réactiver un lien révoqué
 * par erreur (cf. app_share_link_reactivate) avant suppression définitive.
 * À exécuter périodiquement via crontab (cf. project_cron_messenger).
 */
#[AsCommand(name: 'app:share-link:purge-revoked', description: 'Purge les liens de partage révoqués depuis plus de 30 jours')]
final class ShareLinkPurgeRevokedCommand extends Command
{
    public function __construct(
        private readonly ShareLinkRepositoryInterface $shareLinkRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $threshold = (new \DateTimeImmutable())->modify('-' . ShareLink::PURGE_AFTER_DAYS . ' days');
        $purged = $this->shareLinkRepository->deleteRevokedOlderThan($threshold);

        $io->writeln(sprintf('%d lien(s) de partage purgé(s).', $purged));

        return Command::SUCCESS;
    }
}
