<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Share;
use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ShareTest extends TestCase
{
    private function makeUser(): User
    {
        return $this->createMock(User::class);
    }

    private function makeShare(?\DateTimeImmutable $expiresAt = null): Share
    {
        return new Share(
            $this->makeUser(),
            $this->makeUser(),
            Share::RESOURCE_FOLDER,
            Uuid::v7(),
            Share::PERMISSION_READ,
            $expiresAt,
        );
    }

    public function testIsActiveWhenNotExpiredAndNotRevoked(): void
    {
        $share = $this->makeShare(new \DateTimeImmutable('+7 days'));

        $this->assertTrue($share->isActive());
    }

    public function testIsNotActiveWhenExpired(): void
    {
        $share = $this->makeShare(new \DateTimeImmutable('-1 second'));

        $this->assertFalse($share->isActive());
    }

    public function testIsNotActiveWhenRevoked(): void
    {
        $share = $this->makeShare(new \DateTimeImmutable('+7 days'));

        $share->revoke();

        $this->assertFalse($share->isActive());
        $this->assertNotNull($share->getRevokedAt());
    }

    public function testPermanentShareWithNullExpiresAtIsNeverExpired(): void
    {
        $share = $this->makeShare(null);

        $this->assertNull($share->getExpiresAt());
        $this->assertFalse($share->isExpired());
        $this->assertTrue($share->isActive());
    }

    public function testPermanentShareIsNotActiveOnceRevoked(): void
    {
        $share = $this->makeShare(null);

        $share->revoke();

        $this->assertFalse($share->isActive());
    }

    public function testReactivateClearsRevokedAtAndRestoresActiveState(): void
    {
        $share = $this->makeShare(new \DateTimeImmutable('+7 days'));

        $share->revoke();
        $this->assertFalse($share->isActive());

        $share->reactivate();

        $this->assertNull($share->getRevokedAt());
        $this->assertTrue($share->isActive());
    }

    public function testReactivateDoesNotOverrideExpiration(): void
    {
        // Réactiver un partage expiré ne doit pas le rendre actif : la
        // réactivation lève uniquement la révocation manuelle, pas l'expiration.
        $share = $this->makeShare(new \DateTimeImmutable('-1 second'));

        $share->revoke();
        $share->reactivate();

        $this->assertFalse($share->isActive());
        $this->assertTrue($share->isExpired());
    }
}
