<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Security\AdminVoter;
use App\Security\BroadcastAdminChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class AdminVoterTest extends TestCase
{
    private function tokenFor(?User $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }

    private function userWithEmail(string $email): User
    {
        $user = $this->createStub(User::class);
        $user->method('getEmail')->willReturn($email);

        return $user;
    }

    public function testGrantsAccessForWhitelistedAdmin(): void
    {
        $user = $this->userWithEmail('admin@example.com');

        $adminChecker = new BroadcastAdminChecker('admin@example.com');
        $voter = new AdminVoter($adminChecker);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($user), null, [AdminVoter::ADMIN])
        );
    }

    public function testDeniesAccessForNonAdminUser(): void
    {
        $user = $this->userWithEmail('pas-admin@example.com');

        $adminChecker = new BroadcastAdminChecker('admin@example.com');
        $voter = new AdminVoter($adminChecker);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor($user), null, [AdminVoter::ADMIN])
        );
    }

    public function testDeniesAccessWhenNoUserOnToken(): void
    {
        $adminChecker = new BroadcastAdminChecker('admin@example.com');
        $voter = new AdminVoter($adminChecker);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor(null), null, [AdminVoter::ADMIN])
        );
    }

    public function testAbstainsOnUnrelatedAttribute(): void
    {
        $user = $this->userWithEmail('admin@example.com');

        $adminChecker = new BroadcastAdminChecker('admin@example.com');
        $voter = new AdminVoter($adminChecker);

        $this->assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($this->tokenFor($user), null, ['ROLE_USER'])
        );
    }
}
