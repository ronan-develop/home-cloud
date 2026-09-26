<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Media;
use App\Entity\Share;
use App\Entity\User;
use App\Interface\Media\MediaDeletionServiceInterface;
use App\Interface\Media\MediaDetachServiceInterface;
use App\Interface\Share\SharedResourceCleanerInterface;
use App\Interface\File\StorageServiceInterface;
use App\Repository\MediaRepository;
use App\Service\File\FileDeletionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * #446 — FileDeletionService : absorbe la décision détacher-et-conserver
 * (albums) vs suppression complète, jusqu'ici en dur dans
 * FileWebController::delete().
 */
final class FileDeletionServiceTest extends TestCase
{
    private StorageServiceInterface $storage;
    private EntityManagerInterface $em;
    private SharedResourceCleanerInterface $sharedResourceCleaner;
    private MediaRepository $mediaRepository;
    private MediaDetachServiceInterface $mediaDetachService;
    private MediaDeletionServiceInterface $mediaDeletionService;
    private FileDeletionService $service;

    protected function setUp(): void
    {
        $this->storage = $this->createMock(StorageServiceInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->sharedResourceCleaner = $this->createMock(SharedResourceCleanerInterface::class);
        $this->mediaRepository = $this->createMock(MediaRepository::class);
        $this->mediaDetachService = $this->createMock(MediaDetachServiceInterface::class);
        $this->mediaDeletionService = $this->createMock(MediaDeletionServiceInterface::class);

        $this->service = new FileDeletionService(
            $this->storage,
            $this->em,
            $this->sharedResourceCleaner,
            $this->mediaRepository,
            $this->mediaDetachService,
            $this->mediaDeletionService,
        );
    }

    private function makeFile(): File
    {
        $owner = new User('owner@example.com', 'Owner');
        $folder = new Folder('Docs', $owner);

        return new File('rapport.txt', 'text/plain', 42, 'test/rapport.txt', $folder, $owner);
    }

    public function testDeleteFileWithoutMediaCallsStorageDelete(): void
    {
        $file = $this->makeFile();

        $this->mediaRepository->expects($this->once())
            ->method('findByFile')
            ->with($file)
            ->willReturn(null);

        $this->storage->expects($this->once())
            ->method('delete')
            ->with($file->getPath());

        $this->sharedResourceCleaner->expects($this->once())
            ->method('deleteByResource')
            ->with(Share::RESOURCE_FILE, $file->getId());

        $this->em->expects($this->once())->method('remove')->with($file);
        $this->em->expects($this->once())->method('flush');

        $keptInAlbums = $this->service->deleteFile($file, false);

        $this->assertFalse($keptInAlbums);
    }

    public function testDeleteFileWithKeepInAlbumsDetachesMediaAndPreservesIt(): void
    {
        $file = $this->makeFile();
        $media = new Media($file, 'photo');

        $this->mediaRepository->expects($this->once())
            ->method('findByFile')
            ->with($file)
            ->willReturn($media);

        $this->mediaDetachService->expects($this->once())
            ->method('detachAndDeleteFile')
            ->with($media);

        $this->mediaDeletionService->expects($this->never())->method('delete');
        $this->em->expects($this->never())->method('remove');

        $keptInAlbums = $this->service->deleteFile($file, true);

        $this->assertTrue($keptInAlbums);
    }

    public function testDeleteFileWithMediaAndWithoutKeepInAlbumsCallsMediaDeletionService(): void
    {
        $file = $this->makeFile();
        $media = new Media($file, 'photo');

        $this->mediaRepository->expects($this->once())
            ->method('findByFile')
            ->with($file)
            ->willReturn($media);

        $this->mediaDeletionService->expects($this->once())
            ->method('delete')
            ->with($media);

        $this->mediaDetachService->expects($this->never())->method('detachAndDeleteFile');
        $this->storage->expects($this->never())->method('delete');

        $this->sharedResourceCleaner->expects($this->once())
            ->method('deleteByResource')
            ->with(Share::RESOURCE_FILE, $file->getId());

        // Media::$file passé à onDelete: SET NULL (#246) : le File n'est pas
        // supprimé en cascade, MediaDeletionService le fait lui-même.
        $this->em->expects($this->never())->method('remove');
        $this->em->expects($this->once())->method('flush');

        $keptInAlbums = $this->service->deleteFile($file, false);

        $this->assertFalse($keptInAlbums);
    }

    public function testDeleteFilePropagatesStorageException(): void
    {
        $file = $this->makeFile();

        $this->mediaRepository->method('findByFile')->willReturn(null);
        $this->storage->method('delete')->willThrowException(new \RuntimeException('Disque hors service'));

        $this->em->expects($this->never())->method('remove');
        $this->em->expects($this->never())->method('flush');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Disque hors service');

        $this->service->deleteFile($file, false);
    }

    public function testDeleteFilePropagatesDetachException(): void
    {
        $file = $this->makeFile();
        $media = new Media($file, 'photo');

        $this->mediaRepository->method('findByFile')->willReturn($media);
        $this->mediaDetachService->method('detachAndDeleteFile')
            ->willThrowException(new \LogicException('Media déjà détaché'));

        $this->expectException(\LogicException::class);

        $this->service->deleteFile($file, true);
    }
}
