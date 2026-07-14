<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Share;
use App\Entity\User;
use App\Repository\ShareRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class ShareRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ShareRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = static::getContainer()->get(ShareRepository::class);

        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM shares');
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

    public function testDeleteByResourceRemovesMatchingShares(): void
    {
        $owner = $this->createUser('owner-share-repo@example.com');
        $guest = $this->createUser('guest-share-repo@example.com');
        $resourceId = Uuid::v7();

        $share = new Share($owner, $guest, Share::RESOURCE_FOLDER, $resourceId, Share::PERMISSION_READ);
        $this->em->persist($share);
        $this->em->flush();

        $this->repository->deleteByResource(Share::RESOURCE_FOLDER, $resourceId);
        $this->em->clear();

        $this->assertNull($this->repository->find($share->getId()));
    }

    public function testDeleteByResourceLeavesOtherSharesIntact(): void
    {
        $owner = $this->createUser('owner-share-repo2@example.com');
        $guest = $this->createUser('guest-share-repo2@example.com');
        $targetResourceId = Uuid::v7();
        $otherResourceId  = Uuid::v7();

        $targetShare = new Share($owner, $guest, Share::RESOURCE_FOLDER, $targetResourceId, Share::PERMISSION_READ);
        $otherShare  = new Share($owner, $guest, Share::RESOURCE_FOLDER, $otherResourceId, Share::PERMISSION_READ);
        $this->em->persist($targetShare);
        $this->em->persist($otherShare);
        $this->em->flush();

        $this->repository->deleteByResource(Share::RESOURCE_FOLDER, $targetResourceId);
        $this->em->clear();

        $this->assertNull($this->repository->find($targetShare->getId()));
        $this->assertNotNull($this->repository->find($otherShare->getId()));
    }
}
