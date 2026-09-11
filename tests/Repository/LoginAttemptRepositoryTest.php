<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\LoginAttempt;
use App\Repository\LoginAttemptRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LoginAttemptRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private LoginAttemptRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = static::getContainer()->get(LoginAttemptRepository::class);

        $this->em->getConnection()->executeStatement('DELETE FROM login_attempts');
        $this->em->clear();
    }

    private function insertAttempt(
        string $emailHash,
        ?string $ip,
        \DateTimeImmutable $createdAt,
    ): void {
        $attempt = new LoginAttempt($emailHash, $ip, 'TestAgent/1.0');
        $this->em->persist($attempt);
        $this->em->flush();

        // createdAt est fixé dans le constructeur : on force la valeur voulue en base pour tester les fenêtres temporelles.
        $this->em->getConnection()->executeStatement(
            'UPDATE login_attempts SET created_at = ? WHERE id = ?',
            [$createdAt->format('Y-m-d H:i:s'), $attempt->getId()->toBinary()],
        );
        $this->em->clear();
    }

    public function testSavePersistsAttempt(): void
    {
        $attempt = new LoginAttempt(hash('sha256', 'a@example.com'), '10.0.0.1', 'Agent/1.0');

        $this->repository->save($attempt);

        $found = $this->repository->find($attempt->getId());
        $this->assertNotNull($found);
        $this->assertSame($attempt->getEmailHash(), $found->getEmailHash());
    }

    public function testCountRecentByEmailHashCountsOnlyWithinWindow(): void
    {
        $emailHash = hash('sha256', 'victim@example.com');
        $now = new \DateTimeImmutable();

        $this->insertAttempt($emailHash, '10.0.0.1', $now->modify('-5 minutes'));
        $this->insertAttempt($emailHash, '10.0.0.2', $now->modify('-10 minutes'));
        $this->insertAttempt($emailHash, '10.0.0.3', $now->modify('-1 hour'));
        $this->insertAttempt(hash('sha256', 'other@example.com'), '10.0.0.4', $now->modify('-1 minute'));

        $count = $this->repository->countRecentByEmailHash($emailHash, $now->modify('-15 minutes'));

        $this->assertSame(2, $count);
    }

    public function testCountRecentByEmailHashReturnsZeroWhenNoneMatch(): void
    {
        $count = $this->repository->countRecentByEmailHash(hash('sha256', 'nobody@example.com'), new \DateTimeImmutable('-15 minutes'));

        $this->assertSame(0, $count);
    }

    public function testCountRecentByIpCountsOnlyWithinWindow(): void
    {
        $now = new \DateTimeImmutable();

        $this->insertAttempt(hash('sha256', 'a@example.com'), '10.0.0.9', $now->modify('-2 minutes'));
        $this->insertAttempt(hash('sha256', 'b@example.com'), '10.0.0.9', $now->modify('-3 minutes'));
        $this->insertAttempt(hash('sha256', 'c@example.com'), '10.0.0.9', $now->modify('-1 hour'));

        $count = $this->repository->countRecentByIp('10.0.0.9', $now->modify('-15 minutes'));

        $this->assertSame(2, $count);
    }

    public function testFindRecentOrdersByCreatedAtDescending(): void
    {
        $now = new \DateTimeImmutable();

        $this->insertAttempt(hash('sha256', 'old@example.com'), '10.0.0.1', $now->modify('-1 hour'));
        $this->insertAttempt(hash('sha256', 'new@example.com'), '10.0.0.2', $now);

        $recent = $this->repository->findRecent();

        $this->assertCount(2, $recent);
        $this->assertSame(hash('sha256', 'new@example.com'), $recent[0]->getEmailHash());
    }

    public function testFindSuspiciousByEmailHashReturnsOnlyAboveThreshold(): void
    {
        $now = new \DateTimeImmutable();
        $suspicious = hash('sha256', 'attacker-target@example.com');
        $normal = hash('sha256', 'normal-typo@example.com');

        for ($i = 0; $i < 5; ++$i) {
            $this->insertAttempt($suspicious, '10.0.0.1', $now->modify("-{$i} minutes"));
        }
        $this->insertAttempt($normal, '10.0.0.2', $now);

        $results = $this->repository->findSuspiciousByEmailHash($now->modify('-15 minutes'), threshold: 5);

        $this->assertCount(1, $results);
        $this->assertSame($suspicious, $results[0]['emailHash']);
        $this->assertSame(5, $results[0]['count']);
    }

    public function testFindSuspiciousByIpReturnsOnlyAboveThreshold(): void
    {
        $now = new \DateTimeImmutable();

        for ($i = 0; $i < 5; ++$i) {
            $this->insertAttempt(hash('sha256', "victim{$i}@example.com"), '10.0.0.99', $now->modify("-{$i} minutes"));
        }
        $this->insertAttempt(hash('sha256', 'lonely@example.com'), '10.0.0.55', $now);

        $results = $this->repository->findSuspiciousByIp($now->modify('-15 minutes'), threshold: 5);

        $this->assertCount(1, $results);
        $this->assertSame('10.0.0.99', $results[0]['ip']);
        $this->assertSame(5, $results[0]['count']);
    }
}
