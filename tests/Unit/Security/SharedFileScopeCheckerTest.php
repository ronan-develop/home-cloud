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
        $file = $this->createMock(File::class);
        $file->method('getId')->willReturn(Uuid::v7());

        $checker = new SharedFileScopeChecker();

        $this->assertTrue($checker->isInScope($file, Share::RESOURCE_FILE, $file->getId(), $file));
    }

    public function testFileLinkDeniesADifferentFile(): void
    {
        $linkedFileId = Uuid::v7();
        $otherFile = $this->createMock(File::class);
        $otherFile->method('getId')->willReturn(Uuid::v7());

        $checker = new SharedFileScopeChecker();

        $this->assertFalse($checker->isInScope($otherFile, Share::RESOURCE_FILE, $linkedFileId, $otherFile));
    }

    public function testFolderLinkAllowsAFileDirectlyInThatFolder(): void
    {
        $folder = $this->createMock(Folder::class);
        $folder->method('getId')->willReturn(Uuid::v7());

        $file = $this->createMock(File::class);
        $file->method('getFolder')->willReturn($folder);

        $checker = new SharedFileScopeChecker();

        $this->assertTrue($checker->isInScope($file, Share::RESOURCE_FOLDER, $folder->getId(), $folder));
    }

    public function testFolderLinkDeniesAFileInAnotherFolder(): void
    {
        $linkedFolder = $this->createMock(Folder::class);
        $linkedFolder->method('getId')->willReturn(Uuid::v7());

        $otherFolder = $this->createMock(Folder::class);
        $otherFolder->method('getId')->willReturn(Uuid::v7());

        $file = $this->createMock(File::class);
        $file->method('getFolder')->willReturn($otherFolder);

        $checker = new SharedFileScopeChecker();

        $this->assertFalse($checker->isInScope($file, Share::RESOURCE_FOLDER, $linkedFolder->getId(), $linkedFolder));
    }

    public function testAlbumLinkAllowsAFileThatIsAMediaOfThatAlbum(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getId')->willReturn(Uuid::v7());

        $media = $this->createMock(\App\Entity\Media::class);
        $media->method('getFile')->willReturn($file);

        $album = $this->createMock(Album::class);
        $album->method('getMedias')->willReturn(new \Doctrine\Common\Collections\ArrayCollection([$media]));

        $checker = new SharedFileScopeChecker();

        $this->assertTrue($checker->isInScope($file, Share::RESOURCE_ALBUM, $album->getId(), $album));
    }

    public function testAlbumLinkDeniesAFileNotInAlbum(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getId')->willReturn(Uuid::v7());

        $otherFile = $this->createMock(File::class);
        $otherFile->method('getId')->willReturn(Uuid::v7());
        $media = $this->createMock(\App\Entity\Media::class);
        $media->method('getFile')->willReturn($otherFile);

        $album = $this->createMock(Album::class);
        $album->method('getMedias')->willReturn(new \Doctrine\Common\Collections\ArrayCollection([$media]));

        $checker = new SharedFileScopeChecker();

        $this->assertFalse($checker->isInScope($file, Share::RESOURCE_ALBUM, $album->getId(), $album));
    }
}
