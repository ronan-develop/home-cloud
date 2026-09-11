<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Share;
use App\Entity\ShareLink;
use App\Entity\User;
use App\Repository\ShareLinkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class ShareLinkRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ShareLinkRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = static::getContainer()->get(ShareLinkRepository::class);

        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM share_links');
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

    private function createLink(User $owner, string $selector): ShareLink
    {
        return new ShareLink(
            $owner,
            Share::RESOURCE_FILE,
            Uuid::v7(),
            $selector,
            hash('sha256', 'plain-token'),
            null,
        );
    }

    private function setRevokedAt(ShareLink $link, \DateTimeImmutable $revokedAt): void
    {
        $ref = new \ReflectionProperty(ShareLink::class, 'revokedAt');
        $ref->setValue($link, $revokedAt);
    }

    private function setCreatedAt(ShareLink $link, \DateTimeImmutable $createdAt): void
    {
        $ref = new \ReflectionProperty(ShareLink::class, 'createdAt');
        $ref->setValue($link, $createdAt);
    }

    public function testDeleteRevokedOlderThanRemovesOldRevokedLinks(): void
    {
        $owner = $this->createUser('owner-sl-repo@example.com');

        $old = $this->createLink($owner, 'selectorold00000000000000000000');
        $this->setRevokedAt($old, new \DateTimeImmutable('-31 days'));
        $this->em->persist($old);
        $this->em->flush();

        $this->repository->deleteRevokedOlderThan(new \DateTimeImmutable('-30 days'));
        $this->em->clear();

        $this->assertNull($this->repository->find($old->getId()));
    }

    public function testDeleteRevokedOlderThanLeavesRecentlyRevokedLinksIntact(): void
    {
        $owner = $this->createUser('owner-sl-repo2@example.com');

        $recent = $this->createLink($owner, 'selectorrecent0000000000000000');
        $this->setRevokedAt($recent, new \DateTimeImmutable('-1 day'));
        $this->em->persist($recent);
        $this->em->flush();

        $this->repository->deleteRevokedOlderThan(new \DateTimeImmutable('-30 days'));
        $this->em->clear();

        $this->assertNotNull($this->repository->find($recent->getId()));
    }

    public function testDeleteRevokedOlderThanLeavesActiveLinksIntact(): void
    {
        $owner = $this->createUser('owner-sl-repo3@example.com');

        $active = $this->createLink($owner, 'selectoractive0000000000000000');
        $this->em->persist($active);
        $this->em->flush();

        $this->repository->deleteRevokedOlderThan(new \DateTimeImmutable('-30 days'));
        $this->em->clear();

        $this->assertNotNull($this->repository->find($active->getId()));
    }

    public function testCountActiveReturnsZeroWhenNoLinks(): void
    {
        $this->assertSame(0, $this->repository->countActive());
    }

    public function testCountActiveCountsOnlyActiveLinks(): void
    {
        $owner = $this->createUser('owner-sl-count-active@example.com');

        $active = $this->createLink($owner, 'selectoractivecount000000000000');

        $revoked = $this->createLink($owner, 'selectorrevokedcount00000000000');
        $revoked->revoke();

        $expired = new ShareLink(
            $owner,
            Share::RESOURCE_FILE,
            Uuid::v7(),
            'selectorexpiredcount00000000000',
            hash('sha256', 'plain-token'),
            new \DateTimeImmutable('-1 day'),
        );

        $this->em->persist($active);
        $this->em->persist($revoked);
        $this->em->persist($expired);
        $this->em->flush();

        $this->assertSame(1, $this->repository->countActive());
    }

    public function testFindActiveOrderedByCreatedAtReturnsEmptyArrayWhenNoLinks(): void
    {
        $this->assertSame([], $this->repository->findActiveOrderedByCreatedAt());
    }

    public function testFindActiveOrderedByCreatedAtExcludesRevokedAndExpiredLinks(): void
    {
        $owner = $this->createUser('owner-sl-find-active@example.com');

        $active = $this->createLink($owner, 'selectoractivefind0000000000000');

        $revoked = $this->createLink($owner, 'selectorrevokedfind00000000000');
        $revoked->revoke();

        $expired = new ShareLink(
            $owner,
            Share::RESOURCE_FILE,
            Uuid::v7(),
            'selectorexpiredfind00000000000',
            hash('sha256', 'plain-token'),
            new \DateTimeImmutable('-1 day'),
        );

        $this->em->persist($active);
        $this->em->persist($revoked);
        $this->em->persist($expired);
        $this->em->flush();

        $result = $this->repository->findActiveOrderedByCreatedAt();

        $this->assertCount(1, $result);
        $this->assertTrue($result[0]->getId()->equals($active->getId()));
    }

    public function testFindActiveOrderedByCreatedAtOrdersOldestFirst(): void
    {
        $owner = $this->createUser('owner-sl-find-order@example.com');

        $newer = $this->createLink($owner, 'selectornewerorder000000000000');
        $older = $this->createLink($owner, 'selectorolderorder000000000000');
        $this->em->persist($newer);
        $this->em->persist($older);
        $this->em->flush();

        $this->setCreatedAt($newer, new \DateTimeImmutable('-1 day'));
        $this->setCreatedAt($older, new \DateTimeImmutable('-10 days'));
        $this->em->flush();

        $result = $this->repository->findActiveOrderedByCreatedAt();

        $this->assertCount(2, $result);
        $this->assertTrue($result[0]->getId()->equals($older->getId()));
        $this->assertTrue($result[1]->getId()->equals($newer->getId()));
    }
}
