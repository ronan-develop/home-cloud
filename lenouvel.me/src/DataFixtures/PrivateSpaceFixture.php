<?php

namespace App\DataFixtures;

use App\Entity\PrivateSpace;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class PrivateSpaceFixture extends Fixture
{
    public const DEMO_SPACE_REF = 'private-space-demo';

    public function load(ObjectManager $manager): void
    {
        $privateSpace = new PrivateSpace();
        $privateSpace->setName('Espace Démo');
        $privateSpace->setCreatedAt(new \DateTimeImmutable());
        $manager->persist($privateSpace);
        $manager->flush();
        $this->addReference(self::DEMO_SPACE_REF, $privateSpace);
    }
}
