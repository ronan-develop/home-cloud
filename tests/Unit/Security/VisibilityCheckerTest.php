<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Album;
use App\Entity\File;
use App\Entity\Folder;
use App\Security\VisibilityChecker;
use PHPUnit\Framework\TestCase;

final class VisibilityCheckerTest extends TestCase
{
    private function makeFolder(string $visibility, ?Folder $parent = null): Folder
    {
        $folder = $this->createMock(Folder::class);
        $folder->method('getVisibility')->willReturn($visibility);
        $folder->method('getParent')->willReturn($parent);

        return $folder;
    }

    private function makeFile(string $visibility, ?Folder $folder = null): File
    {
        $file = $this->createMock(File::class);
        $file->method('getVisibility')->willReturn($visibility);
        $file->method('getFolder')->willReturn($folder ?? $this->makeFolder(Folder::VISIBILITY_LINK_ALLOWED));

        return $file;
    }

    private function makeAlbum(string $visibility): Album
    {
        $album = $this->createMock(Album::class);
        $album->method('getVisibility')->willReturn($visibility);

        return $album;
    }

    public function testPrivateResourceIsNotPubliclyShareable(): void
    {
        $file = $this->makeFile(File::VISIBILITY_PRIVATE);

        $checker = new VisibilityChecker();

        $this->assertFalse($checker->isPubliclyShareable($file));
    }

    public function testLinkAllowedResourceWithoutParentIsPubliclyShareable(): void
    {
        $file = $this->makeFile(File::VISIBILITY_LINK_ALLOWED, null);

        $checker = new VisibilityChecker();

        $this->assertTrue($checker->isPubliclyShareable($file));
    }

    public function testLinkAllowedFolderIsPubliclyShareable(): void
    {
        $folder = $this->makeFolder(Folder::VISIBILITY_LINK_ALLOWED);

        $checker = new VisibilityChecker();

        $this->assertTrue($checker->isPubliclyShareable($folder));
    }

    public function testLinkAllowedAlbumIsPubliclyShareable(): void
    {
        $album = $this->makeAlbum(Album::VISIBILITY_LINK_ALLOWED);

        $checker = new VisibilityChecker();

        $this->assertTrue($checker->isPubliclyShareable($album));
    }

    public function testLinkAllowedFileInLinkAllowedFolderIsPubliclyShareable(): void
    {
        $folder = $this->makeFolder(Folder::VISIBILITY_LINK_ALLOWED);
        $file = $this->makeFile(File::VISIBILITY_LINK_ALLOWED, $folder);

        $checker = new VisibilityChecker();

        $this->assertTrue($checker->isPubliclyShareable($file));
    }

    public function testLinkAllowedFileInPrivateFolderIsNotPubliclyShareable(): void
    {
        // Le plus restrictif gagne : sinon déplacer un fichier dans un dossier
        // "Privé" ne le protégerait pas, et le verrou ne vaudrait plus rien.
        $folder = $this->makeFolder(Folder::VISIBILITY_PRIVATE);
        $file = $this->makeFile(File::VISIBILITY_LINK_ALLOWED, $folder);

        $checker = new VisibilityChecker();

        $this->assertFalse($checker->isPubliclyShareable($file));
    }

    public function testLinkAllowedFileInLinkAllowedSubfolderOfPrivateFolderIsNotPubliclyShareable(): void
    {
        // La remontée doit parcourir toute la chaîne des parents, pas seulement
        // le parent direct.
        $grandParent = $this->makeFolder(Folder::VISIBILITY_PRIVATE);
        $parent = $this->makeFolder(Folder::VISIBILITY_LINK_ALLOWED, $grandParent);
        $file = $this->makeFile(File::VISIBILITY_LINK_ALLOWED, $parent);

        $checker = new VisibilityChecker();

        $this->assertFalse($checker->isPubliclyShareable($file));
    }

    public function testDenyUnlessPubliclyShareableThrowsOnPrivateResource(): void
    {
        $file = $this->makeFile(File::VISIBILITY_PRIVATE);

        $checker = new VisibilityChecker();

        $this->expectException(\App\Exception\ResourceNotPubliclyShareableException::class);
        $checker->denyUnlessPubliclyShareable($file);
    }

    public function testDenyUnlessPubliclyShareableDoesNotThrowOnShareableResource(): void
    {
        $file = $this->makeFile(File::VISIBILITY_LINK_ALLOWED, null);

        $checker = new VisibilityChecker();

        $checker->denyUnlessPubliclyShareable($file);
        $this->addToAssertionCount(1);
    }
}
