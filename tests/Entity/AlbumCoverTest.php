<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Album;
use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Media;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires — couverture explicite d'un album (Album::coverMedia).
 * TDD RED → GREEN.
 */
final class AlbumCoverTest extends TestCase
{
    private function makeMedia(User $owner, string $name): Media
    {
        $folder = new Folder('Photos', $owner);
        $file   = new File($name, 'image/jpeg', 1024, '2026/' . $name, $folder, $owner);

        return new Media($file, 'photo');
    }

    public function testAlbumHasNoCoverByDefault(): void
    {
        $owner = new User('test@example.com', 'Test');
        $album = new Album('Vacances', $owner);

        $this->assertNull($album->getCoverMedia());
    }

    public function testSetCoverMediaAssignsCoverWhenMediaBelongsToAlbum(): void
    {
        $owner = new User('test@example.com', 'Test');
        $album = new Album('Vacances', $owner);
        $media = $this->makeMedia($owner, 'a.jpg');
        $album->addMedia($media);

        $album->setCoverMedia($media);

        $this->assertSame($media, $album->getCoverMedia());
    }

    public function testSetCoverMediaRejectsMediaNotInAlbum(): void
    {
        $owner = new User('test@example.com', 'Test');
        $album = new Album('Vacances', $owner);
        $foreignMedia = $this->makeMedia($owner, 'foreign.jpg');

        $this->expectException(\InvalidArgumentException::class);

        $album->setCoverMedia($foreignMedia);
    }

    public function testRemovingCoverMediaFromAlbumResetsCover(): void
    {
        $owner = new User('test@example.com', 'Test');
        $album = new Album('Vacances', $owner);
        $media = $this->makeMedia($owner, 'a.jpg');
        $album->addMedia($media);
        $album->setCoverMedia($media);

        $album->removeMedia($media);

        $this->assertNull($album->getCoverMedia());
    }

    public function testRemovingOtherMediaDoesNotResetCover(): void
    {
        $owner = new User('test@example.com', 'Test');
        $album = new Album('Vacances', $owner);
        $m1 = $this->makeMedia($owner, 'a.jpg');
        $m2 = $this->makeMedia($owner, 'b.jpg');
        $album->addMedia($m1);
        $album->addMedia($m2);
        $album->setCoverMedia($m1);

        $album->removeMedia($m2);

        $this->assertSame($m1, $album->getCoverMedia());
    }

    // --- resolveCoverMedia() ---

    public function testResolveCoverMediaReturnsNullForEmptyAlbum(): void
    {
        $owner = new User('test@example.com', 'Test');
        $album = new Album('Vacances', $owner);

        $this->assertNull($album->resolveCoverMedia());
    }

    public function testResolveCoverMediaReturnsExplicitCoverWhenSet(): void
    {
        $owner = new User('test@example.com', 'Test');
        $album = new Album('Vacances', $owner);
        $m1 = $this->makeMedia($owner, 'a.jpg');
        $m1->setThumbnailPath('thumbs/a.jpg');
        $m2 = $this->makeMedia($owner, 'b.jpg');
        $m2->setThumbnailPath('thumbs/b.jpg');
        $album->addMedia($m1);
        $album->addMedia($m2);
        $album->setCoverMedia($m2);

        $this->assertSame($m2, $album->resolveCoverMedia());
    }

    public function testResolveCoverMediaFallsBackToFirstMediaWithThumbnailWhenNoCoverSet(): void
    {
        $owner = new User('test@example.com', 'Test');
        $album = new Album('Vacances', $owner);
        $withoutThumb = $this->makeMedia($owner, 'a.jpg');
        $withThumb = $this->makeMedia($owner, 'b.jpg');
        $withThumb->setThumbnailPath('thumbs/b.jpg');
        $album->addMedia($withoutThumb);
        $album->addMedia($withThumb);

        $this->assertSame($withThumb, $album->resolveCoverMedia());
    }

    public function testResolveCoverMediaReturnsNullWhenNoMediaHasThumbnail(): void
    {
        $owner = new User('test@example.com', 'Test');
        $album = new Album('Vacances', $owner);
        $media = $this->makeMedia($owner, 'a.jpg');
        $album->addMedia($media);

        $this->assertNull($album->resolveCoverMedia());
    }
}
