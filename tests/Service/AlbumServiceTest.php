<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Album;
use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Media;
use App\Entity\User;
use App\Interface\Album\AlbumRepositoryInterface;
use App\Interface\Media\MediaRepositoryInterface;
use App\Interface\Share\SharedResourceCleanerInterface;
use App\Security\GuestRestrictionChecker;
use App\Service\Album\AlbumService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires AlbumService — SRP : création et suppression d'albums.
 * Utilise des mocks pour isoler AlbumService de la couche persistence.
 */
final class AlbumServiceTest extends TestCase
{
    private AlbumService $service;
    /** @var AlbumRepositoryInterface&MockObject */
    private AlbumRepositoryInterface $repository;
    /** @var MediaRepositoryInterface&MockObject */
    private MediaRepositoryInterface $mediaRepository;
    /** @var SharedResourceCleanerInterface&MockObject */
    private SharedResourceCleanerInterface $sharedResourceCleaner;

    protected function setUp(): void
    {
        // Stubs par défaut (aucune vérification d'appel) — chaque test qui a
        // besoin de vérifier un appel précis (expects()) réassigne la
        // propriété concernée avec un createMock() local puis rebuild().
        $this->repository = $this->createStub(AlbumRepositoryInterface::class);
        $this->mediaRepository = $this->createStub(MediaRepositoryInterface::class);
        $this->sharedResourceCleaner = $this->createStub(SharedResourceCleanerInterface::class);
        $this->rebuild();
    }

    private function rebuild(): void
    {
        $this->service = new AlbumService(
            $this->repository,
            $this->mediaRepository,
            $this->sharedResourceCleaner,
            new GuestRestrictionChecker(),
        );
    }

    private function makeUser(): User
    {
        $user = new User('test@example.com', 'Test User');
        return $user;
    }

    private function makeMedia(User $owner): Media
    {
        $folder = new Folder('Photos', $owner);
        $file   = new File('photo.jpg', 'image/jpeg', 1024, '2026/photo.jpg', $folder, $owner);

        return new Media($file, 'photo');
    }

    // --- create() ---

    public function testCreateReturnsAlbumWithCorrectName(): void
    {
        $user = $this->makeUser();
        $this->repository = $this->createMock(AlbumRepositoryInterface::class);
        $this->repository->expects($this->once())->method('save');
        $this->rebuild();

        $album = $this->service->create('Vacances', $user);

        $this->assertInstanceOf(Album::class, $album);
        $this->assertSame('Vacances', $album->getName());
    }

    public function testCreateSetsOwner(): void
    {
        $user = $this->makeUser();
        $this->repository = $this->createMock(AlbumRepositoryInterface::class);
        $this->repository->expects($this->once())->method('save');
        $this->rebuild();

        $album = $this->service->create('Mon Album', $user);

        $this->assertSame($user, $album->getOwner());
    }

    public function testCreateCallsRepositorySave(): void
    {
        $user = $this->makeUser();
        $this->repository = $this->createMock(AlbumRepositoryInterface::class);
        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Album::class));
        $this->rebuild();

        $this->service->create('Test', $user);
    }

    public function testCreateWithEmptyNameThrowsException(): void
    {
        $user = $this->makeUser();
        $this->repository = $this->createMock(AlbumRepositoryInterface::class);
        $this->repository->expects($this->never())->method('save');
        $this->rebuild();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('nom');

        $this->service->create('', $user);
    }

    public function testCreateWithWhitespaceOnlyNameThrowsException(): void
    {
        $user = $this->makeUser();
        $this->repository = $this->createMock(AlbumRepositoryInterface::class);
        $this->repository->expects($this->never())->method('save');
        $this->rebuild();

        $this->expectException(\InvalidArgumentException::class);

        $this->service->create('   ', $user);
    }

    public function testCreateWithMediaIdsAddsOwnedMediaToAlbum(): void
    {
        $user  = $this->makeUser();
        $media = $this->makeMedia($user);

        $this->mediaRepository = $this->createMock(MediaRepositoryInterface::class);
        $this->mediaRepository
            ->expects($this->once())
            ->method('findById')
            ->with($media->getId())
            ->willReturn($media);

        $this->repository = $this->createMock(AlbumRepositoryInterface::class);
        $this->repository->expects($this->once())->method('save');
        $this->rebuild();

        $album = $this->service->create('Vacances', $user, [$media->getId()->toRfc4122()]);

        $this->assertTrue($album->getMedias()->contains($media));
    }

    public function testCreateIgnoresMediaIdNotOwnedByUser(): void
    {
        $user       = $this->makeUser();
        $otherUser  = new User('other@example.com', 'Other');
        $otherMedia = $this->makeMedia($otherUser);

        $this->mediaRepository = $this->createMock(MediaRepositoryInterface::class);
        $this->mediaRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($otherMedia);

        $this->repository = $this->createMock(AlbumRepositoryInterface::class);
        $this->repository->expects($this->once())->method('save');
        $this->rebuild();

        $album = $this->service->create('Vacances', $user, [$otherMedia->getId()->toRfc4122()]);

        $this->assertFalse($album->getMedias()->contains($otherMedia));
    }

    public function testCreateIgnoresUnknownMediaId(): void
    {
        $user = $this->makeUser();

        $this->mediaRepository = $this->createMock(MediaRepositoryInterface::class);
        $this->mediaRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->repository = $this->createMock(AlbumRepositoryInterface::class);
        $this->repository->expects($this->once())->method('save');
        $this->rebuild();

        $album = $this->service->create('Vacances', $user, ['019f5700-0000-7000-8000-000000000000']);

        $this->assertCount(0, $album->getMedias());
    }

    public function testCreateWithoutMediaIdsCreatesEmptyAlbum(): void
    {
        $user = $this->makeUser();
        $this->mediaRepository = $this->createMock(MediaRepositoryInterface::class);
        $this->mediaRepository->expects($this->never())->method('findById');
        $this->repository = $this->createMock(AlbumRepositoryInterface::class);
        $this->repository->expects($this->once())->method('save');
        $this->rebuild();

        $album = $this->service->create('Vacances', $user);

        $this->assertCount(0, $album->getMedias());
    }

    public function testCreateThrowsForGuestAccount(): void
    {
        $guest = new User('guest@example.com', 'Guest');
        $guest->markAsGuest();

        $this->repository = $this->createMock(AlbumRepositoryInterface::class);
        $this->repository->expects($this->never())->method('save');
        $this->rebuild();

        $this->expectException(\App\Exception\GuestNotAllowedException::class);
        $this->service->create('Vacances', $guest);
    }

    // --- setCoverMedia() ---

    public function testSetCoverMediaSetsCoverAndSaves(): void
    {
        $user  = $this->makeUser();
        $album = new Album('Vacances', $user);
        $media = $this->makeMedia($user);
        $album->addMedia($media);

        $this->repository = $this->createMock(AlbumRepositoryInterface::class);
        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($album);
        $this->rebuild();

        $this->service->setCoverMedia($album, $media);

        $this->assertSame($media, $album->getCoverMedia());
    }

    public function testSetCoverMediaThrowsWhenMediaNotInAlbum(): void
    {
        $user         = $this->makeUser();
        $album        = new Album('Vacances', $user);
        $foreignMedia = $this->makeMedia($user);

        $this->repository = $this->createMock(AlbumRepositoryInterface::class);
        $this->repository->expects($this->never())->method('save');
        $this->rebuild();

        $this->expectException(\InvalidArgumentException::class);

        $this->service->setCoverMedia($album, $foreignMedia);
    }

    // --- rename() ---

    public function testRenameSetsNewNameAndSaves(): void
    {
        $user  = $this->makeUser();
        $album = new Album('Ancien nom', $user);

        $this->repository = $this->createMock(AlbumRepositoryInterface::class);
        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($album);
        $this->rebuild();

        $this->service->rename($album, 'Nouveau nom');

        $this->assertSame('Nouveau nom', $album->getName());
    }

    public function testRenameWithEmptyNameThrowsException(): void
    {
        $user  = $this->makeUser();
        $album = new Album('Ancien nom', $user);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->rename($album, '');
    }

    public function testRenameWithWhitespaceOnlyNameThrowsException(): void
    {
        $user  = $this->makeUser();
        $album = new Album('Ancien nom', $user);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->rename($album, '   ');
    }

    public function testRenameDoesNotSaveWhenNameInvalid(): void
    {
        $user  = $this->makeUser();
        $album = new Album('Ancien nom', $user);

        $this->repository = $this->createMock(AlbumRepositoryInterface::class);
        $this->repository->expects($this->never())->method('save');
        $this->rebuild();

        try {
            $this->service->rename($album, '');
        } catch (\InvalidArgumentException) {
        }
    }

    // --- delete() ---

    public function testDeleteCallsRepositoryRemove(): void
    {
        $user = $this->makeUser();
        $album = new Album('À supprimer', $user);

        $this->repository = $this->createMock(AlbumRepositoryInterface::class);
        $this->repository
            ->expects($this->once())
            ->method('remove')
            ->with($album);
        $this->rebuild();

        $this->service->delete($album);
    }
}
