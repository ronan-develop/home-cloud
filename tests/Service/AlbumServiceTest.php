<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Album;
use App\Entity\User;
use App\Interface\AlbumRepositoryInterface;
use App\Service\AlbumService;
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

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AlbumRepositoryInterface::class);
        $this->service = new AlbumService($this->repository);
    }

    private function makeUser(): User
    {
        $user = new User('test@example.com', 'Test User');
        return $user;
    }

    // --- create() ---

    public function testCreateReturnsAlbumWithCorrectName(): void
    {
        $user = $this->makeUser();
        $this->repository->expects($this->once())->method('save');

        $album = $this->service->create('Vacances', $user);

        $this->assertInstanceOf(Album::class, $album);
        $this->assertSame('Vacances', $album->getName());
    }

    public function testCreateSetsOwner(): void
    {
        $user = $this->makeUser();
        $this->repository->expects($this->once())->method('save');

        $album = $this->service->create('Mon Album', $user);

        $this->assertSame($user, $album->getOwner());
    }

    public function testCreateCallsRepositorySave(): void
    {
        $user = $this->makeUser();
        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Album::class));

        $this->service->create('Test', $user);
    }

    public function testCreateWithEmptyNameThrowsException(): void
    {
        $user = $this->makeUser();
        $this->repository->expects($this->never())->method('save');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('nom');

        $this->service->create('', $user);
    }

    public function testCreateWithWhitespaceOnlyNameThrowsException(): void
    {
        $user = $this->makeUser();
        $this->repository->expects($this->never())->method('save');

        $this->expectException(\InvalidArgumentException::class);

        $this->service->create('   ', $user);
    }

    // --- delete() ---

    public function testDeleteCallsRepositoryRemove(): void
    {
        $user = $this->makeUser();
        $album = new Album('À supprimer', $user);

        $this->repository
            ->expects($this->once())
            ->method('remove')
            ->with($album);

        $this->service->delete($album);
    }
}
