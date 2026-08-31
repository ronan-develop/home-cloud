<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testDefaultAccountTypeIsFull(): void
    {
        $user = new User('someone@example.com', 'Someone');

        $this->assertSame(User::ACCOUNT_TYPE_FULL, $user->getAccountType());
        $this->assertFalse($user->isGuest());
    }

    public function testMarkAsGuestSetsAccountTypeToGuest(): void
    {
        $user = new User('guest@example.com', 'Guest');

        $user->markAsGuest();

        $this->assertSame(User::ACCOUNT_TYPE_GUEST, $user->getAccountType());
        $this->assertTrue($user->isGuest());
    }

    public function testLastChangelogViewedAtIsNullByDefault(): void
    {
        $user = new User('someone@example.com', 'Someone');

        $this->assertNull($user->getLastChangelogViewedAt());
    }

    public function testSetLastChangelogViewedAtStoresValue(): void
    {
        $user = new User('someone@example.com', 'Someone');
        $viewedAt = new \DateTimeImmutable('2026-08-01');

        $user->setLastChangelogViewedAt($viewedAt);

        $this->assertSame($viewedAt, $user->getLastChangelogViewedAt());
    }

}
