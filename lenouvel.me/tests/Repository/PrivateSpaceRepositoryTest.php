<?php

namespace App\Tests\Repository;

use App\Repository\PrivateSpaceRepository;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class PrivateSpaceRepositoryTest extends TestCase
{
    public function testConstruct()
    {
        $mockRegistry = $this->createMock(ManagerRegistry::class);
        $repo = new PrivateSpaceRepository($mockRegistry);
        $this->assertInstanceOf(PrivateSpaceRepository::class, $repo);
    }
}
