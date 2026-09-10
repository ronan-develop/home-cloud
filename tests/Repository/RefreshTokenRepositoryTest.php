<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RefreshTokenRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private RefreshTokenRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = static::getContainer()->get(RefreshTokenRepository::class);

        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM refresh_tokens');
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

    public function testDeleteAllForUserRemovesOnlyTargetUserTokens(): void
    {
        $target = $this->createUser('target@example.com');
        $other = $this->createUser('other@example.com');

        $this->em->persist(new RefreshToken($target));
        $this->em->persist(new RefreshToken($target));
        $this->em->persist(new RefreshToken($other));
        $this->em->flush();

        $deleted = $this->repository->deleteAllForUser($target);

        $this->assertSame(2, $deleted);
        $this->assertCount(0, $this->repository->findBy(['user' => $target->getId()]));
        $this->assertCount(1, $this->repository->findBy(['user' => $other->getId()]));
    }

    public function testDeleteAllForUserReturnsZeroWhenNoTokens(): void
    {
        $user = $this->createUser('lonely@example.com');

        $this->assertSame(0, $this->repository->deleteAllForUser($user));
    }
}
