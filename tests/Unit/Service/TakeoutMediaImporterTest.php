<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\ContentFingerprint;
use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Media;
use App\Entity\User;
use App\Interface\File\StorageServiceInterface;
use App\Interface\Media\MediaProcessorInterface;
use App\Interface\MediaDateResolverInterface;
use App\Repository\ContentFingerprintRepository;
use App\Service\Takeout\TakeoutMediaEntry;
use App\Service\Takeout\TakeoutMediaImporter;
use App\Service\Takeout\TakeoutMetadata;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class TakeoutMediaImporterTest extends TestCase
{
    private StorageServiceInterface $storageService;
    private MediaProcessorInterface $mediaProcessor;
    private MediaDateResolverInterface $dateResolver;
    private ContentFingerprintRepository $fingerprintRepository;
    private EntityManagerInterface $em;
    private TakeoutMediaImporter $importer;

    protected function setUp(): void
    {
        // Stubs par défaut (aucune vérification d'appel) — chaque test qui a
        // besoin de vérifier un appel précis (expects()) réassigne la
        // propriété concernée avec un createMock() local.
        $this->storageService = $this->createStub(StorageServiceInterface::class);
        $this->mediaProcessor = $this->createStub(MediaProcessorInterface::class);
        $this->dateResolver = $this->createStub(MediaDateResolverInterface::class);
        $this->fingerprintRepository = $this->createStub(ContentFingerprintRepository::class);
        $this->em = $this->createStub(EntityManagerInterface::class);

        $this->rebuildImporter();
    }

    /**
     * À rappeler après toute réassignation de propriété (ex: createMock()
     * local pour un test qui a besoin d'un expects()) — le constructeur
     * capture les références au moment de l'instanciation.
     */
    private function rebuildImporter(): void
    {
        $this->importer = new TakeoutMediaImporter(
            $this->storageService,
            $this->mediaProcessor,
            $this->dateResolver,
            $this->fingerprintRepository,
            $this->em,
        );
    }

    private function makeExtractedFile(string $content = 'contenu image'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'hc_takeout_media_');
        file_put_contents($path, $content);

        return $path;
    }

    public function testImportSkipsAsDuplicateWhenHashAlreadyKnown(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $folder = new Folder('Import Google Photos 2026-09-26', $owner);
        $extractedPath = $this->makeExtractedFile('contenu déjà connu');
        $hash = hash_file('sha256', $extractedPath);

        $entry = new TakeoutMediaEntry($extractedPath, null);

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->expects($this->never())->method('persist');
        $this->mediaProcessor = $this->createMock(MediaProcessorInterface::class);
        $this->mediaProcessor->expects($this->never())->method('process');
        $this->rebuildImporter();

        $outcome = $this->importer->import($entry, null, $folder, $owner, [$hash]);

        $this->assertTrue($outcome->isDuplicate);
        $this->assertNull($outcome->media);

        unlink($extractedPath);
    }

    public function testImportCreatesFileAndMediaWhenHashUnknown(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $folder = new Folder('Import Google Photos 2026-09-26', $owner);
        $extractedPath = $this->makeExtractedFile('contenu inédit');

        $entry = new TakeoutMediaEntry($extractedPath, null);

        $this->storageService = $this->createMock(StorageServiceInterface::class);
        $this->storageService->expects($this->once())
            ->method('store')
            ->willReturn(['path' => '2026/09/uuid.jpg', 'neutralized' => false]);

        $media = new Media(
            new File('photo.jpg', 'image/jpeg', 10, '2026/09/uuid.jpg', $folder, $owner),
            'photo',
        );
        $this->mediaProcessor = $this->createMock(MediaProcessorInterface::class);
        $this->mediaProcessor->expects($this->once())
            ->method('process')
            ->willReturn($media);

        $this->dateResolver = $this->createMock(MediaDateResolverInterface::class);
        $this->dateResolver->expects($this->once())
            ->method('resolve')
            ->with(null, null)
            ->willReturn(null);

        $persistedTypes = [];
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->expects($this->exactly(2))->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persistedTypes): void {
                $persistedTypes[] = $entity::class;
            });
        $this->em->expects($this->never())->method('flush');
        $this->rebuildImporter();

        $outcome = $this->importer->import($entry, null, $folder, $owner, []);

        $this->assertFalse($outcome->isDuplicate);
        $this->assertSame($media, $outcome->media);
        $this->assertSame([File::class, ContentFingerprint::class], $persistedTypes);

        unlink($extractedPath);
    }

    public function testImportUsesTakeoutMetadataDateViaResolver(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $folder = new Folder('Import Google Photos 2026-09-26', $owner);
        $extractedPath = $this->makeExtractedFile('contenu avec metadata');

        $entry = new TakeoutMediaEntry($extractedPath, $extractedPath . '.json');
        $metadata = new TakeoutMetadata(new \DateTimeImmutable('2020-05-01'), 48.8, 2.3);

        $this->storageService->method('store')->willReturn(['path' => '2026/09/uuid.jpg', 'neutralized' => false]);

        $media = new Media(
            new File('photo.jpg', 'image/jpeg', 10, '2026/09/uuid.jpg', $folder, $owner),
            'photo',
        );
        $media->setTakenAt(new \DateTimeImmutable('2020-04-30'));
        $this->mediaProcessor->method('process')->willReturn($media);

        $resolvedDate = new \DateTimeImmutable('2020-05-01');
        $this->dateResolver = $this->createMock(MediaDateResolverInterface::class);
        $this->dateResolver->expects($this->once())
            ->method('resolve')
            ->with($media->getTakenAt(), $metadata->takenAt)
            ->willReturn($resolvedDate);
        $this->rebuildImporter();

        $outcome = $this->importer->import($entry, $metadata, $folder, $owner, []);

        $this->assertFalse($outcome->isDuplicate);
        $this->assertSame($resolvedDate, $outcome->media->getTakenAt());

        unlink($extractedPath);
    }

    public function testImportReturnsNullMediaWhenFileIsNotAMediaType(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $folder = new Folder('Import Google Photos 2026-09-26', $owner);
        $extractedPath = $this->makeExtractedFile('contenu non-media');

        $entry = new TakeoutMediaEntry($extractedPath, null);

        $this->storageService->method('store')->willReturn(['path' => '2026/09/uuid.json', 'neutralized' => false]);
        $this->mediaProcessor->method('process')->willReturn(null);
        $this->dateResolver = $this->createMock(MediaDateResolverInterface::class);
        $this->dateResolver->expects($this->never())->method('resolve');
        $this->rebuildImporter();

        $outcome = $this->importer->import($entry, null, $folder, $owner, []);

        $this->assertFalse($outcome->isDuplicate);
        $this->assertNull($outcome->media);

        unlink($extractedPath);
    }
}
