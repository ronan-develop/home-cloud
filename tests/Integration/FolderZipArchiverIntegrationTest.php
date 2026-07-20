<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\User;
use App\Interface\FolderZipArchiverInterface;
use App\Interface\StorageServiceInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FolderZipArchiverIntegrationTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    private function createPhysicalFile(StorageServiceInterface $storage, string $relativePath, string $content): void
    {
        $absolutePath = $storage->getAbsolutePath($relativePath);
        @mkdir(dirname($absolutePath), 0777, true);
        file_put_contents($absolutePath, $content);
    }

    public function testArchiveIncludesDirectFilesAndNestedSubfolders(): void
    {
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();
        $storage = $container->get(StorageServiceInterface::class);

        $user = new User('zip-owner@example.com', 'Owner');
        $em->persist($user);

        $parent = new Folder('Parent', $user);
        $child = new Folder('Child', $user, $parent);
        $em->persist($parent);
        $em->persist($child);

        $rootRelative = 'zip-test/' . uniqid() . '.txt';
        $nestedRelative = 'zip-test/' . uniqid() . '.txt';
        $this->createPhysicalFile($storage, $rootRelative, 'contenu racine');
        $this->createPhysicalFile($storage, $nestedRelative, 'contenu imbriqué');

        $rootFile = new File('root.txt', 'text/plain', 14, $rootRelative, $parent, $user, false);
        $nestedFile = new File('nested.txt', 'text/plain', 17, $nestedRelative, $child, $user, false);
        $em->persist($rootFile);
        $em->persist($nestedFile);
        $em->flush();
        $parentId = $parent->getId();
        $em->clear();

        /** @var FolderZipArchiverInterface $archiver */
        $archiver = $container->get(FolderZipArchiverInterface::class);
        $zipPath = $archiver->archive($em->getRepository(Folder::class)->find($parentId));

        $this->assertFileExists($zipPath);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertNotFalse($zip->locateName('Parent/root.txt'));
        $this->assertNotFalse($zip->locateName('Parent/Child/nested.txt'));
        $this->assertSame('contenu racine', $zip->getFromName('Parent/root.txt'));
        $this->assertSame('contenu imbriqué', $zip->getFromName('Parent/Child/nested.txt'));
        $zip->close();

        unlink($zipPath);
    }

    public function testArchiveEmptyFolderProducesValidZipWithDirEntryOnly(): void
    {
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();

        $user = new User('zip-owner-empty@example.com', 'Owner');
        $em->persist($user);
        $folder = new Folder('Empty', $user);
        $em->persist($folder);
        $em->flush();

        /** @var FolderZipArchiverInterface $archiver */
        $archiver = $container->get(FolderZipArchiverInterface::class);
        $zipPath = $archiver->archive($folder);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertSame(1, $zip->numFiles);
        $zip->close();

        unlink($zipPath);
    }

    public function testArchiveSkipsFileMissingFromDisk(): void
    {
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();

        $user = new User('zip-owner-missing@example.com', 'Owner');
        $em->persist($user);
        $folder = new Folder('Broken', $user);
        $em->persist($folder);

        $ghostFile = new File('ghost.txt', 'text/plain', 5, 'zip-test/does-not-exist-' . uniqid() . '.txt', $folder, $user, false);
        $em->persist($ghostFile);
        $em->flush();

        /** @var FolderZipArchiverInterface $archiver */
        $archiver = $container->get(FolderZipArchiverInterface::class);
        $zipPath = $archiver->archive($folder);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertFalse($zip->locateName('Broken/ghost.txt'));
        $zip->close();

        unlink($zipPath);
    }
}
