<?php

namespace App\Tests\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UserRepositoryTest extends KernelTestCase
{
    private UserRepository $repo;
    private \Doctrine\ORM\EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->repo = $this->em->getRepository(User::class);
        // Nettoyage de la table User
        $this->em->createQuery('DELETE FROM App\\Entity\\User u')->execute();
    }

    public function testPersistAndFindUser()
    {
        $user = new User();
        $user->setUsername('repo_test')
            ->setEmail('repo@test.com')
            ->setPassword('secret')
            ->setCreatedAt(new \DateTimeImmutable())
            ->setIsActive(true);
        // Associer un vrai PrivateSpace Doctrine
        $privateSpace = new \App\Entity\PrivateSpace();
        $privateSpace->setName('repo_test_space');
        $privateSpace->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($privateSpace);
        $user->setPrivateSpace($privateSpace);
        $this->em->persist($user);
        $this->em->flush();
        $found = $this->repo->findOneBy(['username' => 'repo_test']);
        $this->assertNotNull($found);
        $this->assertSame('repo_test', $found->getUsername());
    }

    public function testUpgradePasswordThrowsException()
    {
        $user = new User();
        $user->setPassword('old');
        $this->expectException(\LogicException::class);
        $this->repo->upgradePassword($user, 'new');
    }

    public function testUpgradePasswordUpdatesPassword()
    {
        // Création et persistance d'un user
        $user = new User();
        $user->setUsername('upgrade_test')
            ->setEmail('upgrade@test.com')
            ->setPassword('old_hash')
            ->setCreatedAt(new \DateTimeImmutable())
            ->setIsActive(true);
        $privateSpace = new \App\Entity\PrivateSpace();
        $privateSpace->setName('upgrade_test_space');
        $privateSpace->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($privateSpace);
        $user->setPrivateSpace($privateSpace);
        $this->em->persist($user);
        $this->em->flush();
        // Upgrade password
        $this->repo->upgradePassword($user, 'new_hash');
        $this->em->refresh($user);
        $this->assertSame('new_hash', $user->getPassword());
    }
}
