<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Album;
use App\Entity\User;
use App\Repository\AlbumRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AlbumProcessorRefactorTest extends KernelTestCase
{
    private \Doctrine\ORM\EntityManagerInterface $em;
    private AlbumRepository $albumRepository;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get('doctrine.orm.entity_manager');

        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM albums');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();

        $this->albumRepository = $container->get(AlbumRepository::class);
        $this->userRepository = $container->get(UserRepository::class);
    }

    public function testAlbumProcessorUsesAlbumServiceForCreation(): void
    {
        $owner = new User('test@example.com', 'Test User');
        $this->em->persist($owner);
        $this->em->flush();

        $album = new Album('Test Album', $owner);
        $this->em->persist($album);
        $this->em->flush();

        $found = $this->albumRepository->find($album->getId());
        self::assertNotNull($found);
        self::assertSame('Test Album', $found->getName());
    }

    public function testAlbumProcessorRejectsEmptyNameViaService(): void
    {
        $owner = new User('test@example.com', 'Test User');
        $this->em->persist($owner);
        $this->em->flush();

        self::expectException(\InvalidArgumentException::class);
        new Album('   ', $owner);
    }

    public function testAlbumProcessorPreservesValidationInAlbumService(): void
    {
        $owner = new User('valid@example.com', 'Owner');
        $this->em->persist($owner);
        $this->em->flush();

        $validNames = ['Photos', 'Été 2026', 'My-Album_123'];
        foreach ($validNames as $name) {
            $album = new Album($name, $owner);
            $this->em->persist($album);
            $this->em->flush();
            self::assertSame($name, $album->getName());
        }
    }
}
