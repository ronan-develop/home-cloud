<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class UserFixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Utilisateur admin
        $user = new User();
        $user->setUsername('admin');
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword('$2y$13$xp5w9VmPOu6NbUUIRw2kfegQ7wVDP6OBcHhRQTC.t6f2Ke9ldDKG2');
        $manager->persist($user);
        $this->addReference('admin', $user);

        // Utilisateur de test user1@homecloud.test / password
        $user1 = new User();
        $user1->setUsername('user1@homecloud.test');
        $user1->setRoles(['ROLE_USER']);
        // Mot de passe "root" hashé avec bcrypt
        $user1->setPassword('$2y$13$KxhJEH9TFckrdTPROfcsceAiPEmRJmTuFVe3nE1TX1HRWTiwQ5jtm');
        $manager->persist($user1);

        $manager->flush();
    }
}
