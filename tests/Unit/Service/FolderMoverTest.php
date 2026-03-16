<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\User;
use App\Interface\DefaultFolderServiceInterface;
use App\Repository\FolderRepository;
use App\Interface\FolderMoverInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class FolderMoverTest extends TestCase
{
    private FolderRepository $repo;
    private DefaultFolderServiceInterface $defaultFolderService;
    private EntityManagerInterface $em;
    private FolderMoverInterface $mover;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(FolderRepository::class);
        $this->defaultFolderService = $this->createMock(DefaultFolderServiceInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->mover = new \App\Service\FolderMover($this->repo, $this->defaultFolderService, $this->em);
    }

    public function testMoveContentsToUploadsMovesFilesAndReturnsUploads(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $root = new Folder('ToDelete', $owner);
        $child = new Folder('Child', $owner, $root);

        $file1 = new File('one.txt', 'text/plain', 10, 'p/one.txt', $root, $owner);
        $file2 = new File('two.txt', 'text/plain', 20, 'p/two.txt', $child, $owner);

        // Attach files to folders (collections are private but accessible via constructor)
        $root->getFiles()->add($file1);
        $child->getFiles()->add($file2);

        $uploads = new Folder('Uploads', $owner);

        $this->defaultFolderService->expects($this->once())
            ->method('resolve')
            ->with(null, null, $owner)
            ->willReturn($uploads);

        $this->repo->expects($this->once())
            ->method('findDescendantIds')
            ->with($root)
            ->willReturn([$child->getId()->toRfc4122()]);

        // repo->find called for root and child and again in refresh loop -> at least twice
        $this->repo->method('find')->willReturnCallback(function ($id) use ($root, $child) {
            if ($id === $root->getId()->toRfc4122()) {
                return $root;
            }
            if ($id === $child->getId()->toRfc4122()) {
                return $child;
            }

            return null;
        });

        $this->em->expects($this->once())->method('flush');
        $this->em->method('refresh');

        $result = $this->mover->moveContentsToUploads($root, $owner);

        $this->assertSame($uploads, $result);
        $this->assertSame($uploads, $file1->getFolder());
        $this->assertSame($uploads, $file2->getFolder());
    }
}
