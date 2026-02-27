<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class UserTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = false;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->executeStatement('DELETE FROM users');
    }

    public function testGetUserReturns200WithCorrectStructure(): void
    {
        $user = new User('alice@example.com', 'Alice');
        $this->em->persist($user);
        $this->em->flush();

        $client = static::createClient();
        $response = $client->request('GET', '/api/v1/users/'.$user->getId());

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains([
            'email' => 'alice@example.com',
            'displayName' => 'Alice',
        ]);
        $data = $response->toArray();
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('createdAt', $data);
    }

    public function testGetUserReturns404WhenNotFound(): void
    {
        static::createClient()->request('GET', '/api/v1/users/00000000-0000-0000-0000-000000000000');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetCollectionReturnsUsers(): void
    {
        $user1 = new User('bob@example.com', 'Bob');
        $user2 = new User('carol@example.com', 'Carol');
        $this->em->persist($user1);
        $this->em->persist($user2);
        $this->em->flush();

        $response = static::createClient()->request('GET', '/api/v1/users', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertGreaterThanOrEqual(2, count($data));
    }
}
