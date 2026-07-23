<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\User;
use App\Repository\FileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FileRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private FileRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = static::getContainer()->get(FileRepository::class);

        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    private function createUser(string $email): User
    {
        $user = new User($email, 'Test User');
        $user->setPassword('irrelevant-hash');
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function createFile(User $owner, Folder $folder, string $name, int $size): File
    {
        $file = new File($name, 'application/octet-stream', $size, 'irrelevant/path', $folder, $owner);
        $this->em->persist($file);
        $this->em->flush();

        return $file;
    }

    public function testSumSizeByOwnerReturnsZeroWhenNoFiles(): void
    {
        $owner = $this->createUser('owner-empty@example.com');

        $this->assertSame(0, $this->repository->sumSizeByOwner($owner));
    }

    public function testSumSizeByOwnerSumsMultipleFilesOfDifferentSizes(): void
    {
        $owner = $this->createUser('owner-sum@example.com');
        $folder = new Folder('Uploads', $owner);
        $this->em->persist($folder);
        $this->em->flush();

        $this->createFile($owner, $folder, 'a.txt', 100);
        $this->createFile($owner, $folder, 'b.txt', 250);
        $this->createFile($owner, $folder, 'c.txt', 4096);

        $this->assertSame(4446, $this->repository->sumSizeByOwner($owner));
    }

    public function testSumSizeByOwnerExcludesOtherOwnersFiles(): void
    {
        $owner = $this->createUser('owner-isolated@example.com');
        $other = $this->createUser('owner-other@example.com');

        $ownerFolder = new Folder('Uploads', $owner);
        $otherFolder = new Folder('Uploads', $other);
        $this->em->persist($ownerFolder);
        $this->em->persist($otherFolder);
        $this->em->flush();

        $this->createFile($owner, $ownerFolder, 'mine.txt', 500);
        $this->createFile($other, $otherFolder, 'not-mine.txt', 999999);

        $this->assertSame(500, $this->repository->sumSizeByOwner($owner));
    }
}
