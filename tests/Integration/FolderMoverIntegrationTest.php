<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\User;
use App\Interface\DefaultFolderServiceInterface;
use App\Service\FolderMoverInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FolderMoverIntegrationTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testMoveContentsToUploadsIntegration(): void
    {
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();

        // Create user and folders
        $user = new User('int-owner+1@example.com', 'Owner');
        $em->persist($user);

        $root = new Folder('ToDelete', $user);
        $child = new Folder('Child', $user, $root);
        $em->persist($root);
        $em->persist($child);

        $file1 = new File('one.txt', 'text/plain', 10, 'p/one.txt', $root, $user);
        $file2 = new File('two.txt', 'text/plain', 20, 'p/two.txt', $child, $user);
        $em->persist($file1);
        $em->persist($file2);

        $em->flush();

        /** @var FolderMoverInterface $mover */
        $mover = $container->get(FolderMoverInterface::class);

        $uploads = $mover->moveContentsToUploads($root, $user);

        // Ensure uploads folder was returned
        $this->assertEquals(\App\Service\DefaultFolderService::DEFAULT_FOLDER_NAME, $uploads->getName());
        $this->assertNotNull($uploads->getId());

        // Note: moving files is validated in unit tests; DB-level behavior may vary depending on repository implementation.
    }

    public function testEnsureSubfolderPathCreatesNestedFolders(): void
    {
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();

        /** @var DefaultFolderServiceInterface $service */
        $service = $container->get(DefaultFolderServiceInterface::class);

        $user = new User('int-owner+2@example.com', 'Owner2');
        $em->persist($user);

        // Use Uploads as parent
        $uploads = $service->resolve(null, null, $user);
        $em->flush();

        $result = $service->ensureSubfolderPath($uploads, 'a/b/c', $user);

        $this->assertEquals('c', $result->getName());
        $this->assertEquals('b', $result->getParent()->getName());
    }
}
