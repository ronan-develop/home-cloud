<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class UserFixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setUsername('admin');
        $user->setRoles(['ROLE_ADMIN']);
        // Hash généré précédemment pour le mot de passe choisi
        $user->setPassword('$2y$13$xp5w9VmPOu6NbUUIRw2kfegQ7wVDP6OBcHhRQTC.t6f2Ke9ldDKG2');

        $manager->persist($user);
        $manager->flush();
    }
}
