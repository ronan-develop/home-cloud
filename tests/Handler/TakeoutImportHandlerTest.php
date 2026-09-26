<?php

declare(strict_types=1);

namespace App\Tests\Handler;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Media;
use App\Entity\TakeoutImport;
use App\Entity\User;
use App\Handler\TakeoutImportHandler;
use App\Interface\Folder\DefaultFolderServiceInterface;
use App\Message\TakeoutImportMessage;
use App\Repository\ContentFingerprintRepository;
use App\Repository\TakeoutImportRepository;
use App\Service\Takeout\TakeoutImportOutcome;
use App\Service\Takeout\TakeoutMediaEntry;
use App\Service\Takeout\TakeoutMediaImporter;
use App\Service\Takeout\TakeoutMetadata;
use App\Service\Takeout\TakeoutMetadataReader;
use App\Service\Takeout\TakeoutStructureParser;
use App\Service\Takeout\TakeoutStructureResult;
use App\Service\Takeout\TakeoutZipExtractor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class TakeoutImportHandlerTest extends TestCase
{
    private TakeoutImportRepository $importRepository;
    private TakeoutZipExtractor $zipExtractor;
    private TakeoutStructureParser $structureParser;
    private TakeoutMetadataReader $metadataReader;
    private TakeoutMediaImporter $mediaImporter;
    private DefaultFolderServiceInterface $defaultFolderService;
    private ContentFingerprintRepository $fingerprintRepository;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        // Stubs par défaut (aucune vérification d'appel) — chaque test qui a
        // besoin de vérifier un appel précis (expects()) réassigne la
        // propriété concernée avec un createMock() local avant handler().
        $this->importRepository = $this->createStub(TakeoutImportRepository::class);
        $this->zipExtractor = $this->createStub(TakeoutZipExtractor::class);
        $this->structureParser = $this->createStub(TakeoutStructureParser::class);
        $this->metadataReader = $this->createStub(TakeoutMetadataReader::class);
        $this->mediaImporter = $this->createStub(TakeoutMediaImporter::class);
        $this->defaultFolderService = $this->createStub(DefaultFolderServiceInterface::class);
        $this->fingerprintRepository = $this->createStub(ContentFingerprintRepository::class);
        $this->em = $this->createStub(EntityManagerInterface::class);
    }

    /** @var string[] Fichiers temporaires créés par les tests, nettoyés en tearDown */
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->tmpFiles = [];
    }

    private function handler(?LoggerInterface $logger = null): TakeoutImportHandler
    {
        return new TakeoutImportHandler(
            $this->importRepository,
            $this->zipExtractor,
            $this->structureParser,
            $this->metadataReader,
            $this->mediaImporter,
            $this->defaultFolderService,
            $this->fingerprintRepository,
            $this->em,
            $logger ?? new NullLogger(),
        );
    }

    /** Fichier extrait réel — hash_file() a besoin d'un vrai fichier sur disque. */
    private function makeExtractedFile(string $content = 'contenu media'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'hc_takeout_handler_');
        file_put_contents($path, $content);
        $this->tmpFiles[] = $path;

        return $path;
    }

    public function testHandlerDoesNothingWhenImportNotFound(): void
    {
        $this->importRepository->method('find')->willReturn(null);
        $this->zipExtractor = $this->createMock(TakeoutZipExtractor::class);
        $this->zipExtractor->expects($this->never())->method('extract');

        $this->handler()(new TakeoutImportMessage('missing-id', ['/tmp/whatever.zip']));
    }

    public function testHandlerImportsMediaAndMarksCompleted(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $import = new TakeoutImport($owner);
        $folder = new Folder('Import Google Photos 2026-09-26', $owner);

        $this->importRepository->method('find')->willReturn($import);

        $this->zipExtractor = $this->createMock(TakeoutZipExtractor::class);
        $this->zipExtractor->expects($this->once())->method('extract');

        $entry = new TakeoutMediaEntry($this->makeExtractedFile(), null);
        $this->structureParser->method('parse')
            ->willReturn(new TakeoutStructureResult([$entry], 2));

        $this->fingerprintRepository = $this->createMock(ContentFingerprintRepository::class);
        $this->fingerprintRepository->expects($this->once())
            ->method('findExistingHashes')
            ->willReturn([]);

        $this->defaultFolderService = $this->createMock(DefaultFolderServiceInterface::class);
        $this->defaultFolderService->expects($this->once())
            ->method('resolve')
            ->willReturn($folder);

        $media = new Media(
            new File('photo.jpg', 'image/jpeg', 10, '2026/09/uuid.jpg', $folder, $owner),
            'photo',
        );
        $this->mediaImporter = $this->createMock(TakeoutMediaImporter::class);
        $this->mediaImporter->expects($this->once())
            ->method('import')
            ->with($entry, null, $folder, $owner, [])
            ->willReturn(TakeoutImportOutcome::imported($media));

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->expects($this->atLeastOnce())->method('flush');

        $this->handler()(new TakeoutImportMessage((string) $import->getId(), ['/tmp/takeout.zip']));

        $this->assertSame(TakeoutImport::STATUS_COMPLETED, $import->getStatus());
        $this->assertSame(1, $import->getMediaImportedCount());
        $this->assertSame(0, $import->getDuplicatesSkippedCount());
        $this->assertSame(2, $import->getUnrecognizedFilesCount());
    }

    public function testHandlerCountsDuplicatesSeparatelyFromImported(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $import = new TakeoutImport($owner);
        $folder = new Folder('Import Google Photos 2026-09-26', $owner);

        $this->importRepository->method('find')->willReturn($import);

        $entryDuplicate = new TakeoutMediaEntry($this->makeExtractedFile('dup'), null);
        $entryNew = new TakeoutMediaEntry($this->makeExtractedFile('new'), null);
        $this->structureParser->method('parse')
            ->willReturn(new TakeoutStructureResult([$entryDuplicate, $entryNew], 0));

        $this->fingerprintRepository->method('findExistingHashes')->willReturn([]);
        $this->defaultFolderService->method('resolve')->willReturn($folder);

        $media = new Media(
            new File('new.jpg', 'image/jpeg', 10, '2026/09/uuid.jpg', $folder, $owner),
            'photo',
        );
        $this->mediaImporter->method('import')
            ->willReturnOnConsecutiveCalls(
                TakeoutImportOutcome::duplicate(),
                TakeoutImportOutcome::imported($media),
            );

        $this->handler()(new TakeoutImportMessage((string) $import->getId(), ['/tmp/takeout.zip']));

        $this->assertSame(TakeoutImport::STATUS_COMPLETED, $import->getStatus());
        $this->assertSame(1, $import->getMediaImportedCount());
        $this->assertSame(1, $import->getDuplicatesSkippedCount());
    }

    public function testHandlerReadsMetadataWhenEntryHasJsonPath(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $import = new TakeoutImport($owner);
        $folder = new Folder('Import Google Photos 2026-09-26', $owner);

        $this->importRepository->method('find')->willReturn($import);

        $mediaPath = $this->makeExtractedFile();
        $entry = new TakeoutMediaEntry($mediaPath, $mediaPath . '.supplemental-metadata.json');
        $this->structureParser->method('parse')
            ->willReturn(new TakeoutStructureResult([$entry], 0));

        $this->fingerprintRepository->method('findExistingHashes')->willReturn([]);
        $this->defaultFolderService->method('resolve')->willReturn($folder);

        $metadata = new TakeoutMetadata(new \DateTimeImmutable('2020-01-01'), null, null);
        $this->metadataReader = $this->createMock(TakeoutMetadataReader::class);
        $this->metadataReader->expects($this->once())
            ->method('read')
            ->with($entry->metadataPath)
            ->willReturn($metadata);

        $this->mediaImporter = $this->createMock(TakeoutMediaImporter::class);
        $this->mediaImporter->expects($this->once())
            ->method('import')
            ->with($entry, $metadata, $folder, $owner, [])
            ->willReturn(TakeoutImportOutcome::imported(null));

        $this->handler()(new TakeoutImportMessage((string) $import->getId(), ['/tmp/takeout.zip']));
    }

    public function testHandlerMarksFailedAndPreservesCountersOnException(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $import = new TakeoutImport($owner);

        $this->importRepository->method('find')->willReturn($import);

        $this->zipExtractor->method('extract')->willThrowException(new \RuntimeException('disque plein'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $this->handler($logger)(new TakeoutImportMessage((string) $import->getId(), ['/tmp/takeout.zip']));

        $this->assertSame(TakeoutImport::STATUS_FAILED, $import->getStatus());
        $this->assertSame('disque plein', $import->getErrorMessage());
        $this->assertSame(0, $import->getMediaImportedCount());
    }
}
