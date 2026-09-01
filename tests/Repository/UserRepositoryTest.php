<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UserRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UserRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = static::getContainer()->get(UserRepository::class);

        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    private function createUser(string $email, \DateTimeImmutable $createdAt): User
    {
        $user = new User($email, 'Test User');
        $user->setPassword('irrelevant-hash');

        $prop = new \ReflectionProperty(User::class, 'createdAt');
        $prop->setAccessible(true);
        $prop->setValue($user, $createdAt);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function testFindAllOrderedByCreatedAtReturnsEmptyArrayWhenNoUsers(): void
    {
        $this->assertSame([], $this->repository->findAllOrderedByCreatedAt());
    }

    public function testFindAllOrderedByCreatedAtReturnsUsersNewestFirst(): void
    {
        $this->createUser('oldest@example.com', new \DateTimeImmutable('2026-01-01'));
        $this->createUser('newest@example.com', new \DateTimeImmutable('2026-03-01'));
        $this->createUser('middle@example.com', new \DateTimeImmutable('2026-02-01'));

        $result = $this->repository->findAllOrderedByCreatedAt();

        $this->assertSame(
            ['newest@example.com', 'middle@example.com', 'oldest@example.com'],
            array_map(static fn (User $u) => $u->getEmail(), $result),
        );
    }
}
