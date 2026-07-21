<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Share;
use App\Entity\ShareLink;
use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ShareLinkTest extends TestCase
{
    private function makeOwner(): User
    {
        return $this->createMock(User::class);
    }

    public function testIsActiveWhenNotExpiredAndNotRevoked(): void
    {
        $link = new ShareLink(
            $this->makeOwner(),
            Share::RESOURCE_FILE,
            Uuid::v7(),
            'selector0000000000000000000000',
            hash('sha256', 'plain-token'),
            new \DateTimeImmutable('+7 days'),
        );

        $this->assertTrue($link->isActive());
    }

    public function testIsNotActiveWhenExpired(): void
    {
        $link = new ShareLink(
            $this->makeOwner(),
            Share::RESOURCE_FILE,
            Uuid::v7(),
            'selector0000000000000000000000',
            hash('sha256', 'plain-token'),
            new \DateTimeImmutable('-1 second'),
        );

        $this->assertFalse($link->isActive());
    }

    public function testIsNotActiveWhenRevoked(): void
    {
        $link = new ShareLink(
            $this->makeOwner(),
            Share::RESOURCE_FILE,
            Uuid::v7(),
            'selector0000000000000000000000',
            hash('sha256', 'plain-token'),
            new \DateTimeImmutable('+7 days'),
        );

        $link->revoke();

        $this->assertFalse($link->isActive());
    }

    public function testVerifyTokenReturnsTrueForCorrectToken(): void
    {
        $link = new ShareLink(
            $this->makeOwner(),
            Share::RESOURCE_FILE,
            Uuid::v7(),
            'selector0000000000000000000000',
            hash('sha256', 'plain-token'),
            new \DateTimeImmutable('+7 days'),
        );

        $this->assertTrue($link->verifyToken('plain-token'));
    }

    public function testVerifyTokenReturnsFalseForTamperedToken(): void
    {
        $link = new ShareLink(
            $this->makeOwner(),
            Share::RESOURCE_FILE,
            Uuid::v7(),
            'selector0000000000000000000000',
            hash('sha256', 'plain-token'),
            new \DateTimeImmutable('+7 days'),
        );

        $this->assertFalse($link->verifyToken('plain-tokeX'));
    }

    public function testPermanentLinkWithNullExpiresAtIsNeverExpired(): void
    {
        // Cas d'usage : livraison d'un album à un client (photographe), qui
        // ne veut pas surveiller/renouveler un lien qui expirerait tout seul.
        // La révocation manuelle reste le seul moyen de couper l'accès.
        $link = new ShareLink(
            $this->makeOwner(),
            Share::RESOURCE_ALBUM,
            Uuid::v7(),
            'selector0000000000000000000000',
            hash('sha256', 'plain-token'),
            null,
        );

        $this->assertNull($link->getExpiresAt());
        $this->assertFalse($link->isExpired());
        $this->assertTrue($link->isActive());
    }

    public function testPermanentLinkIsNotActiveOnceRevoked(): void
    {
        $link = new ShareLink(
            $this->makeOwner(),
            Share::RESOURCE_ALBUM,
            Uuid::v7(),
            'selector0000000000000000000000',
            hash('sha256', 'plain-token'),
            null,
        );

        $link->revoke();

        $this->assertFalse($link->isActive());
    }

    public function testReactivateClearsRevokedAt(): void
    {
        $link = new ShareLink(
            $this->makeOwner(),
            Share::RESOURCE_FILE,
            Uuid::v7(),
            'selector0000000000000000000000',
            hash('sha256', 'plain-token'),
            new \DateTimeImmutable('+7 days'),
        );

        $link->revoke();
        $link->reactivate();

        $this->assertNull($link->getRevokedAt());
        $this->assertTrue($link->isActive());
    }

    public function testReactivateOnExpiredLinkStaysInactive(): void
    {
        $link = new ShareLink(
            $this->makeOwner(),
            Share::RESOURCE_FILE,
            Uuid::v7(),
            'selector0000000000000000000000',
            hash('sha256', 'plain-token'),
            new \DateTimeImmutable('-1 second'),
        );

        $link->revoke();
        $link->reactivate();

        $this->assertNull($link->getRevokedAt());
        $this->assertFalse($link->isActive());
    }
}
