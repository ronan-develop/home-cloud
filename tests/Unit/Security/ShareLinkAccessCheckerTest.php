<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Share;
use App\Entity\ShareLink;
use App\Entity\User;
use App\Interface\ShareLinkRepositoryInterface;
use App\Security\ShareLinkAccessChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ShareLinkAccessCheckerTest extends TestCase
{
    private function makeShareLink(\DateTimeImmutable $expiresAt, string $plainToken = 'plain-token'): ShareLink
    {
        $owner = $this->createStub(User::class);

        return new ShareLink(
            $owner,
            Share::RESOURCE_FILE,
            Uuid::v7(),
            'selector0000000000000000000000',
            hash('sha256', $plainToken),
            $expiresAt,
        );
    }

    public function testUnknownSelectorReturnsNull(): void
    {
        $repository = $this->createStub(ShareLinkRepositoryInterface::class);
        $repository->method('findBySelector')->willReturn(null);

        $checker = new ShareLinkAccessChecker($repository);

        $this->assertNull($checker->resolve('unknown-selector', 'any-token'));
    }

    public function testInvalidTokenReturnsNull(): void
    {
        $link = $this->makeShareLink(new \DateTimeImmutable('+7 days'));
        $repository = $this->createStub(ShareLinkRepositoryInterface::class);
        $repository->method('findBySelector')->willReturn($link);

        $checker = new ShareLinkAccessChecker($repository);

        $this->assertNull($checker->resolve('selector0000000000000000000000', 'wrong-token'));
    }

    public function testExpiredLinkReturnsNull(): void
    {
        $link = $this->makeShareLink(new \DateTimeImmutable('-1 second'));
        $repository = $this->createStub(ShareLinkRepositoryInterface::class);
        $repository->method('findBySelector')->willReturn($link);

        $checker = new ShareLinkAccessChecker($repository);

        $this->assertNull($checker->resolve('selector0000000000000000000000', 'plain-token'));
    }

    public function testRevokedLinkReturnsNull(): void
    {
        $link = $this->makeShareLink(new \DateTimeImmutable('+7 days'));
        $link->revoke();
        $repository = $this->createStub(ShareLinkRepositoryInterface::class);
        $repository->method('findBySelector')->willReturn($link);

        $checker = new ShareLinkAccessChecker($repository);

        $this->assertNull($checker->resolve('selector0000000000000000000000', 'plain-token'));
    }

    public function testValidLinkReturnsTheShareLink(): void
    {
        $link = $this->makeShareLink(new \DateTimeImmutable('+7 days'));
        $repository = $this->createStub(ShareLinkRepositoryInterface::class);
        $repository->method('findBySelector')->willReturn($link);

        $checker = new ShareLinkAccessChecker($repository);

        $this->assertSame($link, $checker->resolve('selector0000000000000000000000', 'plain-token'));
    }
}
