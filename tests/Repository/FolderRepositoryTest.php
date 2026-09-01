<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Folder;
use App\Entity\User;
use App\Repository\FolderRepository;
use App\Tests\AuthenticatedApiTestCase;

class FolderRepositoryTest extends AuthenticatedApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
        $this->createUser('test@example.com', 'password', 'Test');
    }

    public function testFindAncestorIdsReturnsAllAncestors(): void
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $root = $this->createFolder('Root', $user);
        $child = $this->createFolder('Child', $user, $root);
        $grandchild = $this->createFolder('Grandchild', $user, $child);

        /** @var FolderRepository $repo */
        $repo = self::getContainer()->get(FolderRepository::class);
        $ancestors = $repo->findAncestorIds($grandchild);

        $this->assertContains((string)$child->getId(), $ancestors);
        $this->assertContains((string)$root->getId(), $ancestors);
        $this->assertNotContains((string)$grandchild->getId(), $ancestors);
        $this->assertCount(2, $ancestors);
    }

    public function testFindRootFoldersReturnsOnlyTopLevelFolders(): void
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $root = $this->createFolder('Root', $user);
        $this->createFolder('Child', $user, $root);

        /** @var FolderRepository $repo */
        $repo = self::getContainer()->get(FolderRepository::class);
        $roots = $repo->findRootFolders($user);

        $this->assertCount(1, $roots);
        $this->assertSame('Root', $roots[0]->getName());
    }

    public function testFindRootFoldersReturnsEmptyArrayWhenNoFolders(): void
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);

        /** @var FolderRepository $repo */
        $repo = self::getContainer()->get(FolderRepository::class);

        $this->assertSame([], $repo->findRootFolders($user));
    }
}
