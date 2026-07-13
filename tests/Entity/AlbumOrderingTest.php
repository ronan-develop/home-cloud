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
 * Tests unitaires — ordre des médias dans un album (position persistée
 * via AlbumMedia). TDD RED → GREEN.
 */
final class AlbumOrderingTest extends TestCase
{
    private function makeMedia(User $owner, string $name): Media
    {
        $folder = new Folder('Photos', $owner);
        $file   = new File($name, 'image/jpeg', 1024, '2026/' . $name, $folder, $owner);

        return new Media($file, 'photo');
    }

    public function testAddMediaAssignsIncrementingPosition(): void
    {
        $owner = new User('test@example.com', 'Test');
        $album = new Album('Vacances', $owner);
        $m1 = $this->makeMedia($owner, 'a.jpg');
        $m2 = $this->makeMedia($owner, 'b.jpg');

        $album->addMedia($m1);
        $album->addMedia($m2);

        $medias = $album->getMedias()->toArray();
        $this->assertSame([$m1, $m2], $medias);
    }

    public function testAddMediaIsIdempotent(): void
    {
        $owner = new User('test@example.com', 'Test');
        $album = new Album('Vacances', $owner);
        $media = $this->makeMedia($owner, 'a.jpg');

        $album->addMedia($media);
        $album->addMedia($media);

        $this->assertCount(1, $album->getMedias());
    }

    public function testRemoveMediaRemovesFromCollection(): void
    {
        $owner = new User('test@example.com', 'Test');
        $album = new Album('Vacances', $owner);
        $m1 = $this->makeMedia($owner, 'a.jpg');
        $m2 = $this->makeMedia($owner, 'b.jpg');
        $album->addMedia($m1);
        $album->addMedia($m2);

        $album->removeMedia($m1);

        $this->assertCount(1, $album->getMedias());
        $this->assertFalse($album->getMedias()->contains($m1));
        $this->assertTrue($album->getMedias()->contains($m2));
    }

    public function testReorderChangesMediaOrder(): void
    {
        $owner = new User('test@example.com', 'Test');
        $album = new Album('Vacances', $owner);
        $m1 = $this->makeMedia($owner, 'a.jpg');
        $m2 = $this->makeMedia($owner, 'b.jpg');
        $m3 = $this->makeMedia($owner, 'c.jpg');
        $album->addMedia($m1);
        $album->addMedia($m2);
        $album->addMedia($m3);

        $album->reorder([$m3->getId(), $m1->getId(), $m2->getId()]);

        $medias = $album->getMedias()->toArray();
        $this->assertSame([$m3, $m1, $m2], $medias);
    }

    public function testReorderIgnoresUnknownMediaIds(): void
    {
        $owner = new User('test@example.com', 'Test');
        $album = new Album('Vacances', $owner);
        $m1 = $this->makeMedia($owner, 'a.jpg');
        $m2 = $this->makeMedia($owner, 'b.jpg');
        $album->addMedia($m1);
        $album->addMedia($m2);

        $unknownId = \Symfony\Component\Uid\Uuid::v7();
        $album->reorder([$m2->getId(), $unknownId, $m1->getId()]);

        $medias = $album->getMedias()->toArray();
        $this->assertSame([$m2, $m1], $medias);
    }
}
