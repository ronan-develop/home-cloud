<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Media;
use App\Entity\User;
use App\Interface\StorageServiceInterface;
use App\Service\MediaDeletionService;
use App\Interface\RawPreviewCacheInterface;
use App\Service\RawPreviewCache;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class MediaDeletionServiceTest extends TestCase
{
    private function makeMedia(?string $thumbnailPath = null): Media
    {
        $owner  = new User('test@example.com', 'Test');
        $folder = new Folder('Photos', $owner);
        $file   = new File('photo.jpg', 'image/jpeg', 1024, '2026/02/photo.jpg', $folder, $owner);
        $media  = new Media($file, 'photo');

        if ($thumbnailPath !== null) {
            $media->setThumbnailPath($thumbnailPath);
        }

        return $media;
    }

    public function testDeleteRemovesOriginalFileFromDisk(): void
    {
        $media = $this->makeMedia();

        $storage = $this->createMock(StorageServiceInterface::class);
        $storage->expects($this->once())->method('delete')->with('2026/02/photo.jpg');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('remove');
        $em->expects($this->once())->method('flush');

        $service = new MediaDeletionService($storage, $em, $this->createMock(RawPreviewCacheInterface::class));
        $service->delete($media);
    }

    public function testDeleteRemovesBothMediaAndFileEntities(): void
    {
        // Media::$file est passé à onDelete: SET NULL (#246) : le CASCADE DB
        // qui supprimait le File avec le Media n'existe plus, il faut donc
        // supprimer les deux entités explicitement.
        $media = $this->makeMedia();
        $file  = $media->getFile();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(2))->method('remove')
            ->willReturnCallback(function (object $entity) use ($media, $file) {
                static $expected = true;
                $this->assertContains($entity, [$media, $file]);
            });
        $em->expects($this->once())->method('flush');

        $service = new MediaDeletionService(
            $this->createMock(StorageServiceInterface::class),
            $em,
            $this->createMock(RawPreviewCacheInterface::class),
        );
        $service->delete($media);
    }

    public function testDeleteIsGracefulWhenMediaAlreadyDetached(): void
    {
        // Un Media détaché (#246) n'a plus de File : MediaBulkDeleteController
        // doit pouvoir le supprimer définitivement sans planter.
        $media = $this->makeMedia('thumbs/abc.jpg');
        $media->detach();

        $storage = $this->createMock(StorageServiceInterface::class);
        $storage->expects($this->once())->method('delete')->with('thumbs/abc.jpg');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove')->with($media);
        $em->expects($this->once())->method('flush');

        $cache = $this->createMock(RawPreviewCacheInterface::class);
        $cache->expects($this->never())->method('evict');

        $service = new MediaDeletionService($storage, $em, $cache);
        $service->delete($media);
    }

    public function testDeleteRemovesThumbnailFromDiskWhenPresent(): void
    {
        $media = $this->makeMedia('thumbs/abc.jpg');

        $storage = $this->createMock(StorageServiceInterface::class);
        $storage->expects($this->exactly(2))->method('delete')
            ->willReturnCallback(function (string $path) {
                static $expected = ['2026/02/photo.jpg', 'thumbs/abc.jpg'];
                $this->assertContains($path, $expected);
            });

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('remove');
        $em->method('flush');

        $service = new MediaDeletionService($storage, $em, $this->createMock(RawPreviewCacheInterface::class));
        $service->delete($media);
    }

    public function testDeleteDoesNotFailWhenNoThumbnail(): void
    {
        $media = $this->makeMedia(null);

        $storage = $this->createMock(StorageServiceInterface::class);
        $storage->expects($this->once())->method('delete')->with('2026/02/photo.jpg');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('remove');
        $em->method('flush');

        $service = new MediaDeletionService($storage, $em, $this->createMock(RawPreviewCacheInterface::class));
        $service->delete($media);
    }

    public function testDeleteEvictsCachedRawPreview(): void
    {
        // Une preview de RAW mise en cache pèse ~1 Mo : sans éviction, chaque
        // suppression laisserait un orphelin sur le disque.
        $media = $this->makeMedia();

        $storage = $this->createMock(StorageServiceInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $cache = $this->createMock(RawPreviewCacheInterface::class);
        $cache->expects($this->once())->method('evict')->with('2026/02/photo.jpg');

        $service = new MediaDeletionService($storage, $em, $cache);
        $service->delete($media);
    }

    public function testDeleteIsGracefulWhenFileMissingOnDisk(): void
    {
        $media = $this->makeMedia();

        $storage = $this->createMock(StorageServiceInterface::class);
        $storage->method('delete')->willThrowException(new \RuntimeException('File not found'));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('remove');
        $em->expects($this->once())->method('flush');

        $service = new MediaDeletionService($storage, $em, $this->createMock(RawPreviewCacheInterface::class));
        $service->delete($media);
    }
}
