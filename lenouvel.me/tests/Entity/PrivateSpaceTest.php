<?php

namespace App\Tests\Entity;

use App\Entity\PrivateSpace;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class PrivateSpaceTest extends TestCase
{
    public function testGettersAndSetters()
    {
        $ps = new PrivateSpace();
        $ps->setName('Espace Perso')
            ->setDescription('Mon espace privé')
            ->setCreatedAt(new \DateTimeImmutable());
        $user = $this->createMock(User::class);
        $ps->setUser($user);

        $this->assertSame('Espace Perso', $ps->getName());
        $this->assertSame('Mon espace privé', $ps->getDescription());
        $this->assertInstanceOf(\DateTimeImmutable::class, $ps->getCreatedAt());
        $this->assertSame($user, $ps->getUser());
    }
}
