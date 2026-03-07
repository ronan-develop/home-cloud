<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Folder;
use App\Entity\User;
use App\Repository\FolderRepository;
use App\Service\DefaultFolderService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class DefaultFolderServiceTest extends TestCase
{
    private FolderRepository $repo;
    private EntityManagerInterface $em;
    private DefaultFolderService $service;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(FolderRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->service = new DefaultFolderService($this->repo, $this->em);
    }

    public function testResolveWithExistingFolderIdReturnsFolder(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $folder = new Folder('MyFolder', $owner);

        $this->repo->expects($this->once())
            ->method('find')
            ->with('existing-id')
            ->willReturn($folder);

        $result = $this->service->resolve('existing-id', null, $owner);

        $this->assertSame($folder, $result);
    }

    public function testResolveWithNewFolderNameCreatesAndReturnsFolder(): void
    {
        $owner = new User('u@example.com', 'User');

        $this->em->expects($this->once())
            ->method('persist')
            ->with($this->callback(fn($f) => $f instanceof Folder && $f->getName() === 'NewFolder'));

        $result = $this->service->resolve(null, 'NewFolder', $owner);

        $this->assertInstanceOf(Folder::class, $result);
        $this->assertSame('NewFolder', $result->getName());
    }

    public function testResolveWithNoArgsReturnsExistingUploadsFolder(): void
    {
        $owner = new User('u2@example.com', 'User2');
        $uploads = new Folder(DefaultFolderService::DEFAULT_FOLDER_NAME, $owner);

        $this->repo->expects($this->once())
            ->method('findOneBy')
            ->with(['name' => DefaultFolderService::DEFAULT_FOLDER_NAME, 'owner' => $owner])
            ->willReturn($uploads);

        $result = $this->service->resolve(null, null, $owner);

        $this->assertSame($uploads, $result);
    }

    public function testResolveCreatesUploadsWhenMissing(): void
    {
        $owner = new User('u3@example.com', 'User3');

        $this->repo->expects($this->once())
            ->method('findOneBy')
            ->with(['name' => DefaultFolderService::DEFAULT_FOLDER_NAME, 'owner' => $owner])
            ->willReturn(null);

        $this->em->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Folder::class));

        $result = $this->service->resolve(null, null, $owner);

        $this->assertInstanceOf(Folder::class, $result);
        $this->assertSame(DefaultFolderService::DEFAULT_FOLDER_NAME, $result->getName());
    }

    public function testResolveWithFolderIdBelongingToAnotherOwnerThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $owner = new User('owner-a@example.com', 'A');
        $other = new User('owner-b@example.com', 'B');
        $folder = new Folder('OtherFolder', $other);

        $this->repo->expects($this->once())
            ->method('find')
            ->with('some-id')
            ->willReturn($folder);

        $this->service->resolve('some-id', null, $owner);
    }

    public function testEnsureSubfolderPathCreatesNestedFolders(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $parent = new Folder('Parent', $owner);

        // repo will never find existing child (simulate missing path)
        $this->repo->expects($this->any())
            ->method('findOneBy')
            ->willReturn(null);

        // Expect two persists for A and B
        $this->em->expects($this->exactly(2))
            ->method('persist')
            ->with($this->callback(fn($f) => $f instanceof Folder));

        $this->em->expects($this->once())
            ->method('flush');

        $result = $this->service->ensureSubfolderPath($parent, 'A/B', $owner);

        $this->assertInstanceOf(Folder::class, $result);
        $this->assertSame('B', $result->getName());
        $this->assertSame($parent, $result->getParent());
    }

    public function testParseRelativePathNormalizesAndValidates(): void
    {
        $ref = new \ReflectionMethod(DefaultFolderService::class, 'parseRelativePath');
        $ref->setAccessible(true);

        $input = '2024\\\\Janvier//  March/';
        $segments = $ref->invoke($this->service, $input);

        $this->assertIsArray($segments);
        $this->assertSame(['2024', 'Janvier', 'March'], $segments);

        // Empty path returns empty array
        $this->assertSame([], $ref->invoke($this->service, ''));
    }
}
