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
}
