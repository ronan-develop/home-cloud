<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Album;
use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Share;
use App\Security\SharedFileScopeChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class SharedFileScopeCheckerTest extends TestCase
{
    public function testFileLinkAllowsExactlyTheLinkedFile(): void
    {
        $file = $this->createStub(File::class);
        $file->method('getId')->willReturn(Uuid::v7());

        $checker = new SharedFileScopeChecker();

        $this->assertTrue($checker->isInScope($file, Share::RESOURCE_FILE, $file->getId(), $file));
    }

    public function testFileLinkDeniesADifferentFile(): void
    {
        $linkedFileId = Uuid::v7();
        $otherFile = $this->createStub(File::class);
        $otherFile->method('getId')->willReturn(Uuid::v7());

        $checker = new SharedFileScopeChecker();

        $this->assertFalse($checker->isInScope($otherFile, Share::RESOURCE_FILE, $linkedFileId, $otherFile));
    }

    public function testFolderLinkAllowsAFileDirectlyInThatFolder(): void
    {
        $folder = $this->createStub(Folder::class);
        $folder->method('getId')->willReturn(Uuid::v7());

        $file = $this->createStub(File::class);
        $file->method('getFolder')->willReturn($folder);

        $checker = new SharedFileScopeChecker();

        $this->assertTrue($checker->isInScope($file, Share::RESOURCE_FOLDER, $folder->getId(), $folder));
    }

    public function testFolderLinkDeniesAFileInAnotherFolder(): void
    {
        $linkedFolder = $this->createStub(Folder::class);
        $linkedFolder->method('getId')->willReturn(Uuid::v7());

        $otherFolder = $this->createStub(Folder::class);
        $otherFolder->method('getId')->willReturn(Uuid::v7());

        $file = $this->createStub(File::class);
        $file->method('getFolder')->willReturn($otherFolder);

        $checker = new SharedFileScopeChecker();

        $this->assertFalse($checker->isInScope($file, Share::RESOURCE_FOLDER, $linkedFolder->getId(), $linkedFolder));
    }

    public function testFolderLinkAllowsAFileInASubfolder(): void
    {
        // Partager Docs/ doit couvrir Docs/Sous/fichier.txt, pas seulement
        // les fichiers directement dans Docs/ — cohérent avec VisibilityChecker
        // qui autorise déjà ce fichier par remontée des ancêtres.
        $linkedFolder = $this->createStub(Folder::class);
        $linkedFolder->method('getId')->willReturn(Uuid::v7());

        $subfolder = $this->createStub(Folder::class);
        $subfolder->method('getId')->willReturn(Uuid::v7());
        $subfolder->method('getParent')->willReturn($linkedFolder);

        $file = $this->createStub(File::class);
        $file->method('getFolder')->willReturn($subfolder);

        $checker = new SharedFileScopeChecker();

        $this->assertTrue($checker->isInScope($file, Share::RESOURCE_FOLDER, $linkedFolder->getId(), $linkedFolder));
    }

    public function testFolderLinkDeniesAFileInAnUnrelatedFolderHierarchy(): void
    {
        $linkedFolder = $this->createStub(Folder::class);
        $linkedFolder->method('getId')->willReturn(Uuid::v7());

        $unrelatedGrandParent = $this->createStub(Folder::class);
        $unrelatedGrandParent->method('getId')->willReturn(Uuid::v7());
        $unrelatedGrandParent->method('getParent')->willReturn(null);

        $unrelatedFolder = $this->createStub(Folder::class);
        $unrelatedFolder->method('getId')->willReturn(Uuid::v7());
        $unrelatedFolder->method('getParent')->willReturn($unrelatedGrandParent);

        $file = $this->createStub(File::class);
        $file->method('getFolder')->willReturn($unrelatedFolder);

        $checker = new SharedFileScopeChecker();

        $this->assertFalse($checker->isInScope($file, Share::RESOURCE_FOLDER, $linkedFolder->getId(), $linkedFolder));
    }

    public function testAlbumLinkAllowsAFileThatIsAMediaOfThatAlbum(): void
    {
        $file = $this->createStub(File::class);
        $file->method('getId')->willReturn(Uuid::v7());

        $media = $this->createStub(\App\Entity\Media::class);
        $media->method('getFile')->willReturn($file);

        $album = $this->createStub(Album::class);
        $album->method('getMedias')->willReturn(new \Doctrine\Common\Collections\ArrayCollection([$media]));

        $checker = new SharedFileScopeChecker();

        $this->assertTrue($checker->isInScope($file, Share::RESOURCE_ALBUM, $album->getId(), $album));
    }

    public function testAlbumLinkDeniesAFileNotInAlbum(): void
    {
        $file = $this->createStub(File::class);
        $file->method('getId')->willReturn(Uuid::v7());

        $otherFile = $this->createStub(File::class);
        $otherFile->method('getId')->willReturn(Uuid::v7());
        $media = $this->createStub(\App\Entity\Media::class);
        $media->method('getFile')->willReturn($otherFile);

        $album = $this->createStub(Album::class);
        $album->method('getMedias')->willReturn(new \Doctrine\Common\Collections\ArrayCollection([$media]));

        $checker = new SharedFileScopeChecker();

        $this->assertFalse($checker->isInScope($file, Share::RESOURCE_ALBUM, $album->getId(), $album));
    }
}
