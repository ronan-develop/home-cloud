<?php

namespace App\Tests\Integration;

use App\Entity\Share;
use App\Tests\Integration\DatabaseResetTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ShareIntegrationTest extends KernelTestCase
{
    use DatabaseResetTrait;
    private EntityManagerInterface $em;

    public static function setUpBeforeClass(): void
    {
        self::resetDatabaseAndFixtures();
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testFixturesAreVisibleViaDoctrine(): void
    {
        $repo = $this->em->getRepository(Share::class);
        $all = $repo->findAll();
        $this->assertNotEmpty($all, 'Les entités Share doivent être présentes en base après chargement des fixtures.');
    }
}
