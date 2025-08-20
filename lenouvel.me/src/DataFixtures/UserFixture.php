<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class UserFixture extends Fixture implements DependentFixtureInterface
{
    public function __construct(private UserPasswordHasherInterface $hasher) {}

    public function load(ObjectManager $manager): void
    {
        $privateSpace = $this->getReference(PrivateSpaceFixture::DEMO_SPACE_REF, \App\Entity\PrivateSpace::class);

        $user = new User();
        $user->setUsername('demo')
            ->setEmail('demo@homecloud.local')
            ->setRoles(['ROLE_USER'])
            ->setIsActive(true)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setPrivateSpace($privateSpace);
        $user->setPassword($this->hasher->hashPassword($user, 'password123'));
        $manager->persist($user);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [PrivateSpaceFixture::class];
    }
}
