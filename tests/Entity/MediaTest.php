<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Media;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires — détachement d'un Media de son File source (#246).
 * TDD RED → GREEN.
 */
final class MediaTest extends TestCase
{
    private function makeMedia(User $owner, string $name = 'photo.jpg'): Media
    {
        $folder = new Folder('Photos', $owner);
        $file   = new File($name, 'image/jpeg', 1024, '2026/' . $name, $folder, $owner);

        return new Media($file, 'photo');
    }

    public function testIsOwnedByReturnsTrueForOwner(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $media = $this->makeMedia($owner);

        $this->assertTrue($media->isOwnedBy($owner));
    }

    public function testIsOwnedByReturnsFalseForOtherUser(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $other = new User('other@example.com', 'Other');
        $media = $this->makeMedia($owner);

        $this->assertFalse($media->isOwnedBy($other));
    }

    public function testIsOwnedByStillWorksAfterDetach(): void
    {
        // L'ownership doit rester vérifiable même quand le File source a
        // disparu (Media conservé dans un album après détachement, #246) :
        // isOwnedBy() ne doit donc jamais dépendre de getFile().
        $owner = new User('owner@example.com', 'Owner');
        $media = $this->makeMedia($owner);

        $media->detach();

        $this->assertTrue($media->isOwnedBy($owner));
    }

    public function testDetachSetsFileToNull(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $media = $this->makeMedia($owner);

        $media->detach();

        $this->assertNull($media->getFile());
    }
}
