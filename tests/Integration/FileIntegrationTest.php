<?php

namespace App\Tests\Integration;

use App\Entity\File;
use App\Entity\PrivateSpace;
use App\Tests\Integration\DatabaseResetTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class FileIntegrationTest extends KernelTestCase
{
    use DatabaseResetTrait;
    private EntityManagerInterface $em;

    public static function setUpBeforeClass(): void
    {
        self::resetDatabaseAndFixtures();
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testFilePersistenceAndRelations(): void
    {
        // Création d'un PrivateSpace
        $privateSpace = new PrivateSpace();
        $privateSpace->setName('Espace Test');
        $privateSpace->setDescription('Description de test');
        $this->em->persist($privateSpace);

        // Création d'un File lié à ce PrivateSpace
        $file = new File();
        $file->setFilename('test.txt');
        $file->setPath('/tmp/test.txt');
        $file->setSize(1234);
        $file->setMimeType('text/plain');
        $file->setPrivateSpace($privateSpace);
        $this->em->persist($file);
        $this->em->flush();
        $this->em->clear();

        // Vérification de la récupération
        $repo = $this->em->getRepository(File::class);
        $savedFile = $repo->findOneBy(['filename' => 'test.txt']);
        $this->assertNotNull($savedFile);
        $this->assertEquals('test.txt', $savedFile->getFilename());
        $this->assertInstanceOf(PrivateSpace::class, $savedFile->getPrivateSpace());
        $this->assertEquals('Espace Test', $savedFile->getPrivateSpace()->getName());

        // Suppression
        $this->em->remove($savedFile);
        $this->em->flush();
        $privateSpaceRepo = $this->em->getRepository(PrivateSpace::class);
        $savedPrivateSpace = $privateSpaceRepo->find($savedFile->getPrivateSpace()->getId());
        $this->em->remove($savedPrivateSpace);
        $this->em->flush();
        $this->assertNull($repo->findOneBy(['filename' => 'test.txt']));
    }
}
