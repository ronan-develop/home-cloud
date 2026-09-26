<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\ContentFingerprint;
use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV7;

final class ContentFingerprintTest extends TestCase
{
    public function testConstructorInitializesUuidV7(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $hash = hash('sha256', 'contenu du fichier');

        $fingerprint = new ContentFingerprint($owner, $hash);

        $this->assertInstanceOf(UuidV7::class, $fingerprint->getId());
    }

    public function testConstructorStoresGivenValues(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $hash = hash('sha256', 'contenu du fichier');

        $fingerprint = new ContentFingerprint($owner, $hash);

        $this->assertSame($owner, $fingerprint->getOwner());
        $this->assertSame($hash, $fingerprint->getContentHash());
    }

    public function testConstructorSetsFirstSeenAtToNow(): void
    {
        $owner = new User('owner@example.com', 'Owner');
        $before = new \DateTimeImmutable();

        $fingerprint = new ContentFingerprint($owner, hash('sha256', 'x'));

        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $fingerprint->getFirstSeenAt());
        $this->assertLessThanOrEqual($after, $fingerprint->getFirstSeenAt());
    }
}
