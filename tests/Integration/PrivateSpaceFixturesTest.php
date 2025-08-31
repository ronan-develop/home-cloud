<?php

namespace App\Tests\Integration;

use App\Entity\PrivateSpace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PrivateSpaceFixturesTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        // Reset base et schéma avant chaque test d’intégration
        shell_exec('php bin/console --env=test doctrine:database:create --if-not-exists');
        shell_exec('php bin/console --env=test doctrine:schema:drop --force');
        shell_exec('php bin/console --env=test doctrine:schema:create');
        shell_exec('php bin/console --env=test hautelook:fixtures:load --no-interaction');
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testFixturesAreVisibleViaDoctrine(): void
    {
        $repo = $this->em->getRepository(PrivateSpace::class);
        $all = $repo->findAll();
        $this->assertNotEmpty($all, 'Les entités PrivateSpace doivent être présentes en base après chargement des fixtures.');
    }
}
