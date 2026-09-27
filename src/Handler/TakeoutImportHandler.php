<?php

declare(strict_types=1);

namespace App\Handler;

use App\Entity\User;
use App\Interface\Album\AlbumServiceInterface;
use App\Interface\Folder\DefaultFolderServiceInterface;
use App\Message\TakeoutImportMessage;
use App\Repository\ContentFingerprintRepository;
use App\Repository\TakeoutImportRepository;
use App\Service\Takeout\TakeoutImportOutcome;
use App\Service\Takeout\TakeoutMediaEntry;
use App\Service\Takeout\TakeoutMediaImporter;
use App\Service\Takeout\TakeoutMetadataReader;
use App\Service\Takeout\TakeoutStructureParser;
use App\Service\Takeout\TakeoutZipExtractor;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler Messenger — orchestration complète d'un import Google Takeout
 * (#327). SRP forcé dès l'écriture (cf. plan-327-takeout-import.md) : ce
 * handler ne fait qu'orchestrer — extraction, parcours, boucle par tranches,
 * statut final — jamais de logique d'import d'un média individuel, déléguée
 * à TakeoutMediaImporter.
 *
 * Efficacité I/O et DB : tous les hashs sont calculés puis vérifiés en un
 * seul appel (findExistingHashes), le flush se fait par tranches (jamais un
 * flush par média, jamais un flush unique en fin de lot volumineux).
 * EntityManager::clear() périodique volontairement omis ici : il détacherait
 * $import/$destinationFolder utilisés jusqu'à la fin de la boucle, ce qui
 * forcerait un rechargement risqué à chaque tranche — compromis assumé,
 * acceptable pour la volumétrie visée (cf. plan-327-takeout-import.md).
 *
 * Pas de rollback (tranché) : en cas d'exception, les compteurs reflètent ce
 * qui a réellement été importé jusqu'au crash — markFailed() les reçoit tels
 * quels plutôt que de les remettre à zéro.
 */
#[AsMessageHandler]
final class TakeoutImportHandler
{
    private const FLUSH_BATCH_SIZE = 50;

    public function __construct(
        private readonly TakeoutImportRepository $importRepository,
        private readonly TakeoutZipExtractor $zipExtractor,
        private readonly TakeoutStructureParser $structureParser,
        private readonly TakeoutMetadataReader $metadataReader,
        private readonly TakeoutMediaImporter $mediaImporter,
        private readonly DefaultFolderServiceInterface $defaultFolderService,
        private readonly ContentFingerprintRepository $fingerprintRepository,
        private readonly AlbumServiceInterface $albumService,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public function __invoke(TakeoutImportMessage $message): void
    {
        $import = $this->importRepository->find($message->takeoutImportId);
        if ($import === null) {
            $this->logger->warning('TakeoutImportMessage : import introuvable', [
                'takeoutImportId' => $message->takeoutImportId,
            ]);

            return;
        }

        $mediaImported = 0;
        $duplicatesSkipped = 0;
        $unrecognizedFiles = 0;

        try {
            $import->markExtracting();
            $this->em->flush();

            $workDir = sys_get_temp_dir() . '/takeout-import-' . $import->getId()->toRfc4122();
            foreach ($message->zipPaths as $zipPath) {
                $this->zipExtractor->extract($zipPath, $workDir);
            }

            $import->markProcessing();
            $this->em->flush();

            $structure = $this->structureParser->parse($workDir);
            $unrecognizedFiles = $structure->ignoredCount;

            // Progress bar (#327) : le total n'est connu qu'une fois le
            // parsing terminé — mis à jour ici pour que le front puisse
            // afficher processedCount/totalMediaCount dès ce stade.
            $import->markProcessing(count($structure->mediaEntries));
            $this->em->flush();

            $owner = $import->getOwner();
            $folderName = sprintf('Import Google Photos %s', $import->getCreatedAt()->format('Y-m-d'));
            $destinationFolder = $this->defaultFolderService->resolve(null, $folderName, $owner);

            // Un seul parcours disque pour le hash (déjà garanti par
            // TakeoutStructureParser), une seule requête DB pour les doublons —
            // jamais une requête par fichier sur un lot de plusieurs milliers.
            $hashes = array_map(
                static fn (string $path): string => hash_file('sha256', $path),
                array_map(static fn ($entry) => $entry->mediaPath, $structure->mediaEntries),
            );
            $existingHashes = $this->fingerprintRepository->findExistingHashes($owner, $hashes);

            // Médias groupés par album Google Photos (#478) — null pour un
            // média sans album explicite (racine, ou dossier technique
            // "Photos from <année>", cf. TakeoutStructureParser). Les
            // Album HomeCloud ne peuvent être créés qu'une fois les Media
            // importés (AlbumService::create() attend des mediaIds), donc
            // après la boucle d'import, jamais pendant.
            $mediaIdsByAlbumName = [];

            $processedSinceFlush = 0;
            foreach ($structure->mediaEntries as $entry) {
                $metadata = $entry->metadataPath !== null
                    ? $this->metadataReader->read($entry->metadataPath)
                    : null;

                $outcome = $this->mediaImporter->import($entry, $metadata, $destinationFolder, $owner, $existingHashes);

                if ($outcome->isDuplicate) {
                    ++$duplicatesSkipped;
                } else {
                    ++$mediaImported;
                    $this->collectForAlbum($mediaIdsByAlbumName, $entry, $outcome);
                }
                $import->incrementProcessedCount();

                if (++$processedSinceFlush >= self::FLUSH_BATCH_SIZE) {
                    $this->em->flush();
                    $processedSinceFlush = 0;
                }
            }

            $this->em->flush();

            $this->createAlbums($mediaIdsByAlbumName, $owner);

            $import->markCompleted($mediaImported, $duplicatesSkipped, $unrecognizedFiles);
            $this->em->flush();

            $this->cleanupWorkDir($workDir);
        } catch (\Throwable $e) {
            $this->logger->error('TakeoutImportMessage : échec de l\'import', [
                'takeoutImportId' => $message->takeoutImportId,
                'error' => $e->getMessage(),
            ]);

            $import->markFailed($e->getMessage(), $mediaImported, $duplicatesSkipped, $unrecognizedFiles);
            $this->em->flush();
        }
    }

    /**
     * @param array<string, list<string>> $mediaIdsByAlbumName
     */
    private function collectForAlbum(array &$mediaIdsByAlbumName, TakeoutMediaEntry $entry, TakeoutImportOutcome $outcome): void
    {
        if ($entry->albumName === null || $outcome->media === null) {
            return;
        }

        $mediaIdsByAlbumName[$entry->albumName][] = (string) $outcome->media->getId();
    }

    /**
     * @param array<string, list<string>> $mediaIdsByAlbumName
     */
    private function createAlbums(array $mediaIdsByAlbumName, User $owner): void
    {
        foreach ($mediaIdsByAlbumName as $albumName => $mediaIds) {
            $this->albumService->create($albumName, $owner, $mediaIds);
        }
    }

    private function cleanupWorkDir(string $workDir): void
    {
        if (!is_dir($workDir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($workDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            $fileInfo->isDir() ? rmdir($fileInfo->getPathname()) : unlink($fileInfo->getPathname());
        }

        rmdir($workDir);
    }
}
