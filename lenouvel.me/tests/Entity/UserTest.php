<?php

namespace App\Tests\Entity;

use App\Entity\User;
use App\Entity\PrivateSpace;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testGettersAndSetters()
    {
        $user = new User();
        $user->setUsername('foo')
            ->setEmail('foo@bar.com')
            ->setPassword('secret')
            ->setRoles(['ROLE_USER'])
            ->setIsActive(true);
        $date = new \DateTimeImmutable();
        $user->setCreatedAt($date);
        $privateSpace = $this->createMock(PrivateSpace::class);
        $user->setPrivateSpace($privateSpace);

        $this->assertSame('foo', $user->getUsername());
        $this->assertSame('foo@bar.com', $user->getEmail());
        $this->assertSame('secret', $user->getPassword());
        $this->assertSame(['ROLE_USER'], $user->getRoles());
        $this->assertTrue($user->isActive());
        $this->assertSame($date, $user->getCreatedAt());
        $this->assertSame($privateSpace, $user->getPrivateSpace());
    }

    public function testEraseCredentials()
    {
        $user = new User();
        $user->setPassword('secret');
        $user->eraseCredentials();
        $this->assertSame('secret', $user->getPassword()); // Par défaut, eraseCredentials ne fait rien
    }

    public function testSerialize()
    {
        $user = new User();
        $user->setUsername('foo')->setEmail('foo@bar.com')->setPassword('secret');
        $data = $user->__serialize();
        $this->assertIsArray($data);
        $this->assertArrayHasKey("\0App\\Entity\\User\0username", $data);
        $this->assertArrayHasKey("\0App\\Entity\\User\0email", $data);
        $this->assertArrayHasKey("\0App\\Entity\\User\0password", $data);
        $this->assertSame(hash('crc32c', 'secret'), $data["\0App\\Entity\\User\0password"]);
    }

    public function testUserIdentifier()
    {
        $user = new User();
        $user->setUsername('foo');
        $this->assertSame('foo', $user->getUserIdentifier());
    }
}
