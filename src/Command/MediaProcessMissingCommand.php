<?php

declare(strict_types=1);

namespace App\Command;

use App\Interface\FileRepositoryInterface;
use App\Interface\MediaProcessorInterface;
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
 */
#[AsCommand(name: 'app:media:process-missing', description: 'Traite les fichiers restés sans Media (vignette manquante)')]
final class MediaProcessMissingCommand extends Command
{
    public function __construct(
        private readonly FileRepositoryInterface $fileRepository,
        private readonly MediaProcessorInterface $mediaProcessor,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $files = $this->fileRepository->findWithoutMedia();

        $processed = 0;
        $skipped = 0;

        foreach ($files as $file) {
            if ($this->mediaProcessor->process($file) !== null) {
                ++$processed;
            } else {
                ++$skipped;
            }
        }

        $io->writeln(sprintf('%d traité(s), %d ignoré(s) (type non média).', $processed, $skipped));

        return Command::SUCCESS;
    }
}
