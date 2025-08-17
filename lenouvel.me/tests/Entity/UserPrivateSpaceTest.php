<?php

namespace App\Tests\Entity;

use App\Entity\User;
use App\Entity\PrivateSpace;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use PHPUnit\Framework\Assert;

class UserPrivateSpaceTest extends KernelTestCase
{
    public function testUserPrivateSpaceBidirectionalRelation(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();

        // Création d'un User
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('testuser@example.com');
        $user->setPassword('hashedpassword');
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setIsActive(true);

        // Création d'un PrivateSpace
        $privateSpace = new PrivateSpace();
        $privateSpace->setName('Espace Test');
        $privateSpace->setDescription('Espace privé de test');
        $privateSpace->setCreatedAt(new \DateTimeImmutable());

        // Association bidirectionnelle
        $user->setPrivateSpace($privateSpace);
        $privateSpace->setUser($user);

        // Persistance
        $em->persist($user);
        $em->persist($privateSpace);
        $em->flush();
        $em->clear();

        // Récupération depuis la base
        $userRepo = $em->getRepository(User::class);
        $psRepo = $em->getRepository(PrivateSpace::class);
        $userFromDb = $userRepo->findOneBy(['username' => 'testuser']);
        $psFromDb = $psRepo->findOneBy(['name' => 'Espace Test']);

        // Vérification de la relation dans les deux sens
        Assert::assertNotNull($userFromDb->getPrivateSpace());
        Assert::assertEquals('Espace Test', $userFromDb->getPrivateSpace()->getName());
        Assert::assertNotNull($psFromDb->getUser());
        Assert::assertEquals('testuser', $psFromDb->getUser()->getUsername());
    }
}
