<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Security\UserAccountStatusChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserAccountStatusCheckerTest extends TestCase
{
    public function testCheckPreAuthThrowsWhenUserInactive(): void
    {
        $user = new User('inactive@example.com', 'Inactive');
        $user->deactivate();

        $checker = new UserAccountStatusChecker();

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $checker->checkPreAuth($user);
    }

    public function testCheckPreAuthAllowsActiveUser(): void
    {
        $user = new User('active@example.com', 'Active');
        $checker = new UserAccountStatusChecker();

        $checker->checkPreAuth($user);

        $this->addToAssertionCount(1);
    }

    public function testCheckPostAuthThrowsWhenUserInactive(): void
    {
        $user = new User('inactive@example.com', 'Inactive');
        $user->deactivate();

        $checker = new UserAccountStatusChecker();

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $checker->checkPostAuth($user);
    }

    public function testCheckPreAuthIgnoresNonUserInstances(): void
    {
        $checker = new UserAccountStatusChecker();

        $checker->checkPreAuth($this->createMock(UserInterface::class));

        $this->addToAssertionCount(1);
    }
}
