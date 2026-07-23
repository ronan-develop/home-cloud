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

    public function testFindActiveByGuestReturnsOnlyActiveSharesForThatGuest(): void
    {
        $owner  = $this->createUser('owner-find-active-guest@example.com');
        $guestA = $this->createUser('guest-a-find-active@example.com');
        $guestB = $this->createUser('guest-b-find-active@example.com');

        $shareA = new Share($owner, $guestA, Share::RESOURCE_FOLDER, Uuid::v7(), Share::PERMISSION_READ);
        $shareB = new Share($owner, $guestB, Share::RESOURCE_FOLDER, Uuid::v7(), Share::PERMISSION_READ);
        $this->em->persist($shareA);
        $this->em->persist($shareB);
        $this->em->flush();

        $result = $this->repository->findActiveByGuest($guestA);

        $this->assertCount(1, $result);
        $this->assertTrue($result[0]->getId()->equals($shareA->getId()));
    }

    public function testFindActiveByGuestExcludesExpiredShares(): void
    {
        $owner = $this->createUser('owner-expired-guest@example.com');
        $guest = $this->createUser('guest-expired@example.com');

        $expired = new Share(
            $owner,
            $guest,
            Share::RESOURCE_FOLDER,
            Uuid::v7(),
            Share::PERMISSION_READ,
            new \DateTimeImmutable('-1 day'),
        );
        $this->em->persist($expired);
        $this->em->flush();

        $result = $this->repository->findActiveByGuest($guest);

        $this->assertSame([], $result);
    }

    public function testFindActiveByGuestExcludesRevokedShares(): void
    {
        $owner = $this->createUser('owner-revoked-guest@example.com');
        $guest = $this->createUser('guest-revoked@example.com');

        $revoked = new Share($owner, $guest, Share::RESOURCE_FOLDER, Uuid::v7(), Share::PERMISSION_READ);
        $revoked->revoke();
        $this->em->persist($revoked);
        $this->em->flush();

        $result = $this->repository->findActiveByGuest($guest);

        $this->assertSame([], $result);
    }

    public function testFindActiveByGuestReturnsEmptyArrayWhenNoShares(): void
    {
        $guest = $this->createUser('guest-no-shares@example.com');

        $result = $this->repository->findActiveByGuest($guest);

        $this->assertSame([], $result);
    }

    public function testFindActiveByGuestOrdersByCreatedAtDescending(): void
    {
        $owner = $this->createUser('owner-order-guest@example.com');
        $guest = $this->createUser('guest-order@example.com');

        $older = new Share($owner, $guest, Share::RESOURCE_FOLDER, Uuid::v7(), Share::PERMISSION_READ);
        $newer = new Share($owner, $guest, Share::RESOURCE_FOLDER, Uuid::v7(), Share::PERMISSION_READ);
        $this->em->persist($older);
        $this->em->persist($newer);
        $this->em->flush();

        $reflection = new \ReflectionProperty(Share::class, 'createdAt');
        $reflection->setValue($older, new \DateTimeImmutable('-2 days'));
        $reflection->setValue($newer, new \DateTimeImmutable('-1 day'));
        $this->em->flush();

        $result = $this->repository->findActiveByGuest($guest);

        $this->assertCount(2, $result);
        $this->assertTrue($result[0]->getId()->equals($newer->getId()));
        $this->assertTrue($result[1]->getId()->equals($older->getId()));
    }
}
