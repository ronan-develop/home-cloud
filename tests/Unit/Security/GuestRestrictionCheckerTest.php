<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Exception\GuestNotAllowedException;
use App\Security\GuestRestrictionChecker;
use PHPUnit\Framework\TestCase;

final class GuestRestrictionCheckerTest extends TestCase
{
    private function makeUser(bool $isGuest): User
    {
        $user = $this->createMock(User::class);
        $user->method('isGuest')->willReturn($isGuest);

        return $user;
    }

    public function testDenyUnlessFullAccountThrowsForGuest(): void
    {
        $checker = new GuestRestrictionChecker();

        $this->expectException(GuestNotAllowedException::class);
        $checker->denyUnlessFullAccount($this->makeUser(true));
    }

    public function testDenyUnlessFullAccountDoesNotThrowForFullAccount(): void
    {
        $checker = new GuestRestrictionChecker();

        $checker->denyUnlessFullAccount($this->makeUser(false));
        $this->addToAssertionCount(1);
    }
}
