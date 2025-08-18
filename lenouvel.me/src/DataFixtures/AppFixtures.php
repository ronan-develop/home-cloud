<?php

namespace App\DataFixtures;

use App\Entity\PrivateSpace;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Création d'un espace privé de test
        $privateSpace = new PrivateSpace();
        $privateSpace->setName('Espace de test');
        $privateSpace->setDescription('Espace privé pour les tests fonctionnels');
        $privateSpace->setCreatedAt(new \DateTimeImmutable());
        $manager->persist($privateSpace);

        // Création d'un utilisateur de test lié à cet espace
        $user = new User();
        $user->setUsername('demo');
        $user->setEmail('demo@example.com');
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setIsActive(true);
        $user->setRoles([]);
        $user->setPrivateSpace($privateSpace);
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, 'password123')
        );
        $manager->persist($user);

        $manager->flush();
    }
}
