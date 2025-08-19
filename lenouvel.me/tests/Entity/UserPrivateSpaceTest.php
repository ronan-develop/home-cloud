<?php

namespace App\Tests\Entity;

use App\Entity\User;
use App\Entity\PrivateSpace;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Doctrine\ORM\EntityManagerInterface;

class UserPrivateSpaceTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        // Nettoyage de la base avant chaque test
        $this->em->createQuery('DELETE FROM App\\Entity\\PrivateSpace ps')->execute();
        $this->em->createQuery('DELETE FROM App\\Entity\\User u')->execute();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
        unset($this->em);
    }

    public function testUserPrivateSpaceBidirectionalRelation(): void
    {
        // Création d'un User avec username unique
        $uniqueUsername = 'testuser_' . uniqid();
        $user = new User();
        $user->setUsername($uniqueUsername);
        $user->setEmail($uniqueUsername . '@example.com');
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
        $this->em->persist($user);
        $this->em->persist($privateSpace);
        $this->em->flush();
        $this->em->clear();

        // Récupération depuis la base
        $userRepo = $this->em->getRepository(User::class);
        $psRepo = $this->em->getRepository(PrivateSpace::class);
        $userFromDb = $userRepo->findOneBy(['username' => $uniqueUsername]);
        $psFromDb = $psRepo->findOneBy(['name' => 'Espace Test']);

        // Vérification de la relation dans les deux sens
        $this->assertNotNull($userFromDb->getPrivateSpace());
        $this->assertEquals('Espace Test', $userFromDb->getPrivateSpace()->getName());
        $this->assertNotNull($psFromDb->getUser());
        $this->assertEquals($uniqueUsername, $psFromDb->getUser()->getUsername());
    }
}
