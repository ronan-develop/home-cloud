<?php

declare(strict_types=1);

namespace App\Command;

use App\Interface\File\FileRepositoryInterface;
use App\Interface\Media\MediaProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rattrape les fichiers restés sans Media (donc sans vignette en Galerie).
 *
 * Cas d'usage : des fichiers uploadés via un chemin qui ne dispatchait pas
 * MediaProcessMessage (ex : route web legacy avant correction) restent
 * indéfiniment sans vignette, le worker ne les voyant jamais passer.
 * MediaProcessor::process() étant idempotent, relancer cette commande sans
 * rien à traiter est sans risque.
 *
 * #365 : sur un rattrapage de plusieurs centaines de fichiers, l'UnitOfWork
 * accumulait toutes les entités déjà traitées sans jamais les libérer,
 * jusqu'à l'OOM kill du cron. FileRepository::findWithoutMedia() itère
 * maintenant (toIterable()) au lieu de tout hydrater d'un coup, et l'EntityManager
 * est détaché après chaque fichier — pas seulement tous les N — puisque
 * MediaProcessor::process() flush déjà individuellement : rien n'est en
 * attente à perdre entre deux fichiers.
 */
#[AsCommand(name: 'app:media:process-missing', description: 'Traite les fichiers restés sans Media (vignette manquante)')]
final class MediaProcessMissingCommand extends Command
{
    public function __construct(
        private readonly FileRepositoryInterface $fileRepository,
        private readonly MediaProcessorInterface $mediaProcessor,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $files = $this->fileRepository->findWithoutMedia();

        $processed = 0;
        $skipped = 0;

        // #504 : sans catch, une exception levée au milieu de la boucle
        // (ex. disque plein sur le Nième fichier) remonte à Symfony Console,
        // stack trace complète dans les logs crontab. Le travail déjà
        // accompli avant l'exception n'est pas perdu (chaque fichier flush
        // individuellement, cf. commentaire de classe) — seule l'erreur est
        // catchée et loggée, pas de rollback.
        try {
            foreach ($files as $file) {
                if ($this->mediaProcessor->process($file) !== null) {
                    ++$processed;
                } else {
                    ++$skipped;
                }

                $this->entityManager->clear();
            }

            $io->writeln(sprintf('%d traité(s), %d ignoré(s) (type non média).', $processed, $skipped));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->logger->error('MediaProcessMissingCommand : échec du rattrapage', ['exception' => $e]);
            $io->writeln(sprintf('%d traité(s), %d ignoré(s) avant l\'échec.', $processed, $skipped));
            $io->error('Le rattrapage des vignettes manquantes a échoué.');

            return Command::FAILURE;
        }
    }
}
